<?php

namespace UWMadison\LocalInstrumentLibrary;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use MetaData;
use REDCap;
use Files;
use ZipArchive;

class LocalInstrumentLibrary extends AbstractExternalModule
{
    public function redcap_module_system_enable($version)
    {
        if (empty($this->getSystemSetting("storage_project"))) {
            $project_id = $this->createProject("Local Instrument Library", 1, "Stores local instruments for easy sharing");
            $this->setSystemSetting("storage_project", $project_id);
        }

        if (empty($this->getSystemSetting("public_library_title")))
            $this->setSystemSetting("public_library_title", "Local Instrument Library");

        if (empty($this->getSystemSetting("public_library_blurb")))
            $this->setSystemSetting("public_library_blurb", "Browse pre-approved data collection instruments. Select from validated clinical scales, standard demographics questionnaires, and specialized screeners.");

        if (empty($this->getSystemSetting("internal_library_title")))
            $this->setSystemSetting("internal_library_title", "Local Instrument Library");

        if (empty($this->getSystemSetting("internal_library_blurb")))
            $this->setSystemSetting("internal_library_blurb", "Browse and install pre-approved data collection instruments directly into your current REDCap project. Select from validated clinical scales, standard demographics questionnaires, and specialized screeners.");

        if ($this->getSystemSetting("show_demo_instruments") === null)
            $this->setSystemSetting("show_demo_instruments", true);
    }

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
            $('body').on('click', '#local-library-btn', (e) => {
                e.preventDefault();
                window.location.href = $(e.currentTarget).attr('href');
            });
        </script>
<?php
    }

    public function redcap_module_ajax($action, $payload, $project_id)
    {
        $config = $this->getConfig();
        if (!in_array($action, $config['auth-ajax-actions'] ?? []))
            return ['status' => 'error', 'message' => 'Unauthorized or invalid action.'];

        if ($action === 'submit')
            return $this->handleSubmitAction($payload, $project_id);

        if ($action === 'install')
            return $this->handleInstallAction($payload, $project_id);

        if ($action === 'uninstall')
            return $this->handleUninstallAction($payload, $project_id);

        return ['status' => 'error', 'message' => 'Invalid action.'];
    }

    private function handleZipSubmission($payload)
    {
        $fileData = base64_decode($payload['fileData'] ?? '');
        if (empty($fileData))
            return ['status' => 'error', 'message' => 'No file data received.'];
        $tempFile = tempnam(sys_get_temp_dir(), 'libzip');
        file_put_contents($tempFile, $fileData);

        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Failed to open ZIP archive.'];
        }
        $hasCSV = $zip->locateName('instrument.csv') !== false;
        $hasOrigin = $zip->locateName('OriginID.txt') !== false;
        if (!$hasCSV || !$hasOrigin) {
            $zip->close();
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'ZIP archive must contain instrument.csv and OriginID.txt at the root.'];
        }

        $csvContent = $zip->getFromName('instrument.csv');
        $zip->close();

        $lines = explode("\n", $csvContent);
        $headers = [];
        $firstLine = true;
        $nameIdx = 0;
        $typeIdx = 3;
        $labelIdx = 4;
        $fields = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (empty($row) || count($row) < 2) continue;
            if ($firstLine) {
                $headers = array_map('trim', $row);
                $nameIdx = array_search("Variable / Field Name", $headers);
                $typeIdx = array_search("Field Type", $headers);
                $labelIdx = array_search("Field Label", $headers);
                if ($nameIdx === false) $nameIdx = 0;
                if ($typeIdx === false) $typeIdx = 3;
                if ($labelIdx === false) $labelIdx = 4;
                $firstLine = false;
                continue;
            }
            $var = $row[$nameIdx] ?? '';
            $typeField = $row[$typeIdx] ?? '';
            $label = $row[$labelIdx] ?? '';
            if (!empty($var))
                $fields[] = [
                    'label' => $label,
                    'variable' => $var,
                    'type' => $typeField
                ];
        }

        $storage_pid = $this->getSystemSetting("storage_project");
        if (empty($storage_pid)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Storage project not configured. Please enable the module first.'];
        }

        $originalName = $payload['fileName'] ?? 'instrument.zip';
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $version = $payload['version'] ?? '1.0.0';
        $fileName = $base . "_" . $version . "." . $ext;

        $file = [
            'name' => $fileName,
            'tmp_name' => $tempFile,
            'size' => filesize($tempFile)
        ];
        $doc_id = Files::uploadFile($file, $storage_pid);
        if (empty($doc_id)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Failed to save ZIP file to storage project.'];
        }

        // Add file to the File Repository of the storage project
        REDCap::addFileToRepository($doc_id, $storage_pid, "Uploaded from Instrument Library by " . ExternalModules::getUsername());

        return ['status' => 'success', 'fields' => $fields, 'doc_id' => $doc_id];
    }

    private function handleProjectSubmission($payload, $project_id)
    {
        $instName = $payload['instrumentName'] ?? '';
        if (empty($instName))
            return ['status' => 'error', 'message' => 'No instrument selected.'];
        $metadata = $this->getMetadata($project_id, $instName);
        if (empty($metadata))
            return ['status' => 'error', 'message' => 'Instrument not found or has no fields.'];

        $fp = fopen('php://temp', 'r+');
        $headers = [
            "Variable / Field Name",
            "Form Name",
            "Section Header",
            "Field Type",
            "Field Label",
            "Choices, Calculations, OR Slider Labels",
            "Field Note",
            "Text Validation Type OR Show Slider Number",
            "Text Validation Min",
            "Text Validation Max",
            "Identifier?",
            "Branching Logic",
            "Required?",
            "Custom Alignment",
            "Question Number",
            "Matrix Group Name",
            "Matrix Ranking?",
            "Field Annotation"
        ];
        fputcsv($fp, $headers);

        $fields = [];
        foreach ($metadata as $f) {
            $row = [
                $f['field_name'] ?? '',
                $f['form_name'] ?? '',
                $f['section_header'] ?? '',
                $f['field_type'] ?? '',
                $f['field_label'] ?? '',
                $f['select_choices_or_calculations'] ?? '',
                $f['field_note'] ?? '',
                $f['text_validation_type_or_show_slider_number'] ?? '',
                $f['text_validation_min'] ?? '',
                $f['text_validation_max'] ?? '',
                $f['identifier'] ?? '',
                $f['branching_logic'] ?? '',
                $f['required_field'] ?? '',
                $f['custom_alignment'] ?? '',
                $f['question_number'] ?? '',
                $f['matrix_group_name'] ?? '',
                $f['matrix_ranking'] ?? '',
                $f['field_annotation'] ?? ''
            ];
            fputcsv($fp, $row);

            $fields[] = [
                'label' => $f['field_label'] ?? '',
                'variable' => $f['field_name'] ?? '',
                'type' => $f['field_type'] ?? ''
            ];
        }
        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        $originId = APP_PATH_WEBROOT_FULL . "index.php?pid=" . $project_id;
        $tempFile = tempnam(sys_get_temp_dir(), 'projzip');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            return ['status' => 'error', 'message' => 'Failed to create ZIP package.'];
        $zip->addFromString('instrument.csv', $csvContent);
        $zip->addFromString('OriginID.txt', $originId);
        $zip->close();

        $storage_pid = $this->getSystemSetting("storage_project");
        if (empty($storage_pid)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Storage project not configured. Please enable the module first.'];
        }

        $version = $payload['version'] ?? '1.0.0';
        $fileName = $instName . "_instrument_" . $version . ".zip";

        $file = [
            'name' => $fileName,
            'tmp_name' => $tempFile,
            'size' => filesize($tempFile)
        ];
        $doc_id = Files::uploadFile($file, $storage_pid);
        if (empty($doc_id)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Failed to save ZIP file to storage project.'];
        }

        // Add file to the File Repository of the storage project
        REDCap::addFileToRepository($doc_id, $storage_pid, "Uploaded from Instrument Library by " . ExternalModules::getUsername());

        return ['status' => 'success', 'fields' => $fields, 'doc_id' => $doc_id];
    }

    private function handleSubmitAction($payload, $project_id)
    {
        $subType = $payload['subType'] ?? 'new';
        $type = $payload['type'] ?? 'zip';
        $title = $payload['title'] ?? '';
        $description = $payload['description'] ?? '';
        $author_name = $payload['author_name'] ?? '';
        $author_email = $payload['author_email'] ?? '';
        $version = $payload['version'] ?? '1.0.0';
        $time = $payload['time'] ?? '';
        $categories = $payload['categories'] ?? [];

        if ($subType === 'new' && empty($title))
            return ['status' => 'error', 'message' => 'Instrument title is required.'];

        if ($type === 'zip')
            $result = $this->handleZipSubmission($payload);
        elseif ($type === 'project')
            $result = $this->handleProjectSubmission($payload, $project_id);
        else
            return ['status' => 'error', 'message' => 'Invalid submission type.'];

        if ($result['status'] === 'error')
            return $result;

        $fields = $result['fields'];
        $doc_id = $result['doc_id'];

        $uploaded_instruments = $this->loadUploadedInstruments();

        if ($subType === 'update') {
            $target_inst = null;
            $is_existing_uploaded = false;
            $existingId = $payload['existingId'] ?? '';
            foreach ($uploaded_instruments as $idx => $inst) {
                if ($inst['id'] === $existingId) {
                    $target_inst = &$uploaded_instruments[$idx];
                    $is_existing_uploaded = true;
                    break;
                }
            }

            if (!$target_inst) {
                $instruments = $this->getInstruments();
                foreach ($instruments as $inst) {
                    if ($inst['id'] === $existingId) {
                        $target_inst = $inst;
                        break;
                    }
                }
            }

            if (!$target_inst)
                return ['status' => 'error', 'message' => 'Target instrument not found for update.'];

            $target_inst['title'] = !empty($title) ? $title : $target_inst['title'];
            $target_inst['categories'] = !empty($categories) ? $categories : $target_inst['categories'];
            $target_inst['description'] = !empty($description) ? $description : $target_inst['description'];
            $target_inst['author_name'] = !empty($author_name) ? $author_name : $target_inst['author_name'];
            $target_inst['author_email'] = !empty($author_email) ? $author_email : $target_inst['author_email'];
            $target_inst['time'] = !empty($time) ? $time : $target_inst['time'];
            $target_inst['fields'] = $fields;

            $new_version_obj = [
                'version' => $version,
                'doc_id' => $doc_id,
                'submitted_by' => ExternalModules::getUsername(),
                'submitted_date' => date('Y-m-d')
            ];
            $target_inst['versions'][] = $new_version_obj;

            if (!$is_existing_uploaded)
                $uploaded_instruments[] = $target_inst;
        } else {
            $clean_id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $title));
            $clean_id = preg_replace('/_+/', '_', $clean_id);
            $clean_id = trim($clean_id, '_');

            $instruments = $this->getInstruments();
            $id_exists = false;
            foreach ($instruments as $inst) {
                if ($inst['id'] === $clean_id) $id_exists = true;
            }
            if ($id_exists)
                $clean_id .= '_' . time();

            $new_inst = [
                'id' => $clean_id,
                'title' => $title,
                'categories' => $categories,
                'description' => $description,
                'author_name' => $author_name,
                'author_email' => $author_email,
                'time' => $time,
                'installs' => 0,
                'fields' => $fields,
                'versions' => [
                    [
                        'version' => $version,
                        'doc_id' => $doc_id,
                        'submitted_by' => ExternalModules::getUsername(),
                        'submitted_date' => date('Y-m-d')
                    ]
                ]
            ];
            $uploaded_instruments[] = $new_inst;
        }

        $this->saveUploadedInstruments($uploaded_instruments);
        return ['status' => 'success', 'message' => 'Instrument successfully submitted.'];
    }

    private function handleInstallAction($payload, $project_id)
    {
        $inst_id = $payload['id'] ?? null;
        if (empty($inst_id))
            return ['status' => 'error', 'message' => 'Missing instrument ID.'];

        $instruments = $this->getInstruments();
        $target_inst = null;
        foreach ($instruments as $inst) {
            if ($inst['id'] === $inst_id) {
                $target_inst = $inst;
                break;
            }
        }

        if (!$target_inst)
            return ['status' => 'error', 'message' => 'Instrument not found in library.'];

        $metadata = $this->getMetadata($project_id);
        $all_instruments = [];
        $all_fields = [];
        if (is_array($metadata)) {
            foreach ($metadata as $field) {
                $all_instruments[$field['form_name']] = $field['form_name'];
                $all_fields[] = $field['field_name'];
            }
        }

        $errors = [];
        if (isset($all_instruments[$inst_id]))
            $errors[] = "An instrument named '$inst_id' already exists in this project.";

        $overlapping_fields = [];
        foreach ($target_inst['fields'] as $field) {
            $var = $field['variable'];
            if (in_array($var, $all_fields))
                $overlapping_fields[] = $var;
        }
        if (!empty($overlapping_fields))
            $errors[] = "The following field names already exist in this project: " . implode(', ', $overlapping_fields) . ".";

        if (!empty($errors))
            return [
                'status' => 'error',
                'message' => "Cannot install. " . implode(' ', $errors)
            ];

        $current_dd = $this->getMetadata($project_id);
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

        list($fieldCount, $saveErrors) = MetaData::saveMetadataFlat($current_dd);
        if (!empty($saveErrors))
            return [
                'status' => 'error',
                'message' => 'Failed to save metadata: ' . implode(' ', (array)$saveErrors)
            ];

        $installed_instruments = $this->getProjectSetting('installed_instruments', $project_id) ?? [];
        if (!in_array($inst_id, $installed_instruments)) {
            $installed_instruments[] = $inst_id;
            $this->setProjectSetting('installed_instruments', $installed_instruments, $project_id);
        }

        return ['status' => 'success', 'installed' => $installed_instruments];
    }

    private function handleUninstallAction($payload, $project_id)
    {
        $inst_id = $payload['id'] ?? null;
        if (empty($inst_id))
            return ['status' => 'error', 'message' => 'Missing instrument ID.'];

        $installed_instruments = $this->getProjectSetting('installed_instruments', $project_id) ?? [];
        if (($key = array_search($inst_id, $installed_instruments)) !== false) {
            unset($installed_instruments[$key]);
            $installed_instruments = array_values($installed_instruments);
            $this->setProjectSetting('installed_instruments', $installed_instruments, $project_id);
        }

        return ['status' => 'success', 'message' => 'Removed instrument'];
    }

    public function getInstruments($includeDemos = null)
    {
        // Default to the system setting when not explicitly specified
        if ($includeDemos === null)
            $includeDemos = (bool)$this->getSystemSetting('show_demo_instruments');

        $combined = [];

        // Load demo instruments from defaults.json when enabled
        if ($includeDemos) {
            $defaults = $this->getDefaults();
            $domain = $this->getContactEmailDomain();
            foreach ($defaults['demo_instruments'] ?? [] as $inst) {
                if (!empty($inst['author_email']))
                    $inst['author_email'] = preg_replace('/@(.*)$/', '@' . $domain, $inst['author_email']);
                $combined[$inst['id']] = $inst;
            }
        }

        // Uploaded (real) instruments always override demos with the same ID
        foreach ($this->loadUploadedInstruments() as $inst)
            $combined[$inst['id']] = $inst;

        // Resolve the latest version string for every instrument
        foreach ($combined as &$inst) {
            $latest = "0.0.0";
            if (isset($inst['versions']) && is_array($inst['versions'])) {
                foreach ($inst['versions'] as $vObj) {
                    $v = $vObj['version'];
                    $vClean = ltrim($v, 'v');
                    $parts1 = explode('.', $vClean);
                    $parts2 = explode('.', $latest);
                    $isGreater = false;
                    for ($i = 0; $i < max(count($parts1), count($parts2)); $i++) {
                        $p1 = isset($parts1[$i]) ? (int)$parts1[$i] : 0;
                        $p2 = isset($parts2[$i]) ? (int)$parts2[$i] : 0;
                        if ($p1 > $p2) {
                            $isGreater = true;
                            break;
                        }
                        if ($p1 < $p2)
                            break;
                    }
                    if ($isGreater)
                        $latest = $vClean;
                }
            }
            $inst['version'] = 'v' . $latest;
        }
        unset($inst);

        return array_values($combined);
    }

    public function getDefaults()
    {
        $path = __DIR__ . '/defaults.json';
        if (!file_exists($path))
            return [];
        return json_decode(file_get_contents($path), true);
    }

    public function getContactEmailDomain()
    {
        $sql = $this->query("SELECT value FROM redcap_config WHERE field_name = 'homepage_contact_email'", []);
        $email = db_fetch_assoc($sql)['value'] ?? '';
        $parts = explode('@', $email);
        return count($parts) === 2 ? $this->escape($parts[1]) : 'example.edu';
    }

    public function loadUploadedInstruments()
    {
        $chunk1 = $this->getSystemSetting("instruments_metadata_1");
        if ($chunk1 === null) {
            $uploaded = $this->getSystemSetting("instruments_metadata");
            if (is_array($uploaded))
                return $uploaded;
            if (is_string($uploaded)) {
                $decoded = json_decode($uploaded, true);
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        }

        $chunks = [];
        $chunks[] = $this->getSystemSetting("instruments_metadata");

        $i = 1;
        while (true) {
            $chunk = $this->getSystemSetting("instruments_metadata_" . $i);
            if ($chunk === null)
                break;
            $chunks[] = $chunk;
            $i++;
        }

        $json_str = implode('', $chunks);
        $decoded = json_decode($json_str, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function saveUploadedInstruments($uploaded_instruments)
    {
        $json_str = json_encode($uploaded_instruments);
        $chunk_size = 60000;

        if (strlen($json_str) > $chunk_size) {
            $chunks = str_split($json_str, $chunk_size);
            $this->setSystemSetting("instruments_metadata", $chunks[0]);

            for ($i = 1; $i < count($chunks); $i++)
                $this->setSystemSetting("instruments_metadata_" . $i, $chunks[$i]);

            $i = count($chunks);
            while (true) {
                $setting_name = "instruments_metadata_" . $i;
                $existing = $this->getSystemSetting($setting_name);
                if ($existing === null)
                    break;
                $this->removeSystemSetting($setting_name);
                $i++;
            }
            return;
        }

        $this->setSystemSetting("instruments_metadata", $uploaded_instruments);

        $i = 1;
        while (true) {
            $setting_name = "instruments_metadata_" . $i;
            $existing = $this->getSystemSetting($setting_name);
            if ($existing === null)
                break;
            $this->removeSystemSetting($setting_name);
            $i++;
        }
    }
}
