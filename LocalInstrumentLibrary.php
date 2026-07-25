<?php

namespace UWMadison\LocalInstrumentLibrary;

use ExternalModules\AbstractExternalModule;
use REDCap;
use MetaData;

class LocalInstrumentLibrary extends AbstractExternalModule
{
    public function redcap_every_page_top($project_id)
    {
        if (!$this->isPage('Design/online_designer.php') || empty($project_id))
            return;

        $library_url = $this->getUrl('index.php');
?>
        <script type="text/javascript">
            $(document).ready(() => {
                let button = $('button:contains("Upload")')
                if (button.length === 0)
                    return;

                let newButton = button.clone();
                newButton.attr('id', 'local-library-btn');
                newButton.attr('href', '<?= $library_url; ?>');
                newButton.html('<i class="fas fa-book-open"></i> Local Instrument Library');
                newButton.attr('style', '')
                newButton.removeAttr('onclick');

                let newContainer = button.closest('div').clone();
                newContainer.html(newButton[0].outerHTML);

                button.closest('div').after(newContainer);
            });
            $('body').on('click', '#local-library-btn', function(e) {
                e.preventDefault();
                window.location.href = $(this).attr('href');
            });
        </script>
<?php
    }

    public function redcap_module_ajax($action, $payload, $project_id)
    {
        $config = $this->getConfig();
        if (!in_array($action, $config['auth-ajax-actions'] ?? []))
            return ['status' => 'error', 'message' => 'Unauthorized or invalid action.'];

        $inst_id = $payload['id'] ?? null;
        if (empty($inst_id))
            return ['status' => 'error', 'message' => 'Missing instrument ID.'];

        // Load dummy data
        require_once __DIR__ . '/dummy_data.php';

        // Find target instrument details
        $target_inst = null;
        foreach ($instruments as $inst) {
            if ($inst['id'] === $inst_id) {
                $target_inst = $inst;
                break;
            }
        }

        if (!$target_inst)
            return ['status' => 'error', 'message' => 'Instrument not found in library.'];

        // Get existing instrument names and field names using REDCap APIs (SQL-free)
        $all_instruments = REDCap::getInstrumentNames(null, $project_id);
        $all_fields = REDCap::getFieldNames();

        if ($action === 'install') {
            $errors = [];

            // 1. Check if an instrument with the same name already exists in the project
            if (isset($all_instruments[$inst_id])) {
                $errors[] = "An instrument named '$inst_id' already exists in this project.";
            }

            // 2. Check if there is an overlap with any field names
            $overlapping_fields = [];
            foreach ($target_inst['fields'] as $field) {
                $var = $field['variable'];
                if (in_array($var, $all_fields)) {
                    $overlapping_fields[] = $var;
                }
            }
            if (!empty($overlapping_fields)) {
                $errors[] = "The following field names already exist in this project: " . implode(', ', $overlapping_fields) . ".";
            }

            if (!empty($errors)) {
                return [
                    'status' => 'error',
                    'message' => "Cannot install. " . implode(' ', $errors)
                ];
            }

            // 3. No issue: Get current data dictionary, append a placeholder descriptive field, and save it
            $current_dd = REDCap::getDataDictionary($project_id, 'array');

            $placeholder_field = $inst_id . "_placeholder";
            $new_field = [
                "field_name" => $placeholder_field,
                "form_name" => $inst_id,
                "section_header" => "",
                "field_type" => "descriptive",
                "field_label" => "Start of instrument: " . $target_inst['title'],
                "select_choices_or_calculations" => "",
                "field_note" => "",
                "text_validation_type_or_show_slider_number" => "",
                "text_validation_min" => "",
                "text_validation_max" => "",
                "identifier" => "",
                "branching_logic" => "",
                "required_field" => "",
                "custom_alignment" => "",
                "question_number" => "",
                "matrix_group_name" => "",
                "matrix_ranking" => "",
                "field_annotation" => ""
            ];

            $current_dd[] = $new_field;

            // Save the updated data dictionary using REDCap's metadata save utility
            list($fieldCount, $saveErrors) = MetaData::saveMetadataFlat($current_dd);

            if (!empty($saveErrors)) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to save metadata: ' . implode(' ', (array)$saveErrors)
                ];
            }

            // Save installed status in project setting
            $installed_instruments = $this->getProjectSetting('installed_instruments', $project_id) ?? [];
            if (!in_array($inst_id, $installed_instruments)) {
                $installed_instruments[] = $inst_id;
                $this->setProjectSetting('installed_instruments', $installed_instruments, $project_id);
            }

            return ['status' => 'success', 'installed' => $installed_instruments];
        } elseif ($action === 'uninstall') {

            $installed_instruments = $this->getProjectSetting('installed_instruments', $project_id) ?? [];
            if (($key = array_search($inst_id, $installed_instruments)) !== false) {
                unset($installed_instruments[$key]);
                $installed_instruments = array_values($installed_instruments);
                $this->setProjectSetting('installed_instruments', $installed_instruments, $project_id);
            }

            return ['status' => 'success', 'message' => 'Removed instrument'];
        }

        return ['status' => 'error', 'message' => 'Invalid action.'];
    }
}
