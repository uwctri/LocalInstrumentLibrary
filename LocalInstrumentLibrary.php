<?php

namespace UWMadison\LocalInstrumentLibrary;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use MetaData;
use REDCap;
use Files;
use ZipArchive;
use Exception;

class LocalInstrumentLibrary extends AbstractExternalModule
{
    public function redcap_module_system_enable($version)
    {
        if (empty($this->getSystemSetting("storage_project"))) {
            $project_id = $this->createProject("Local Instrument Library", 1, "Stores local instruments for easy sharing");
            $this->setSystemSetting("storage_project", $project_id);
        }

        $defaults = $this->getDefaults();
        $init_settings = $defaults['default_settings'] ?? [];

        foreach ($init_settings as $key => $default_value) {
            $val = $this->getSystemSetting($key);
            if ($val === null || $val === "")
                $this->setSystemSetting($key, $default_value);
        }
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

        if ($action === 'save-settings')
            return $this->handleSaveSettingsAction($payload);

        if ($action === 'approve-submission')
            return $this->handleApproveSubmissionAction($payload);

        if ($action === 'decline-submission')
            return $this->handleDeclineSubmissionAction($payload);

        if ($action === 'save-metadata')
            return $this->handleSaveMetadataAction($payload);

        return ['status' => 'error', 'message' => 'Invalid action.'];
    }

    private function handleSaveSettingsAction($payload)
    {
        if (!SUPER_USER)
            return ['status' => 'error', 'message' => 'Only REDCap Administrators can modify system settings.'];

        if (isset($payload['show_demo_instruments']))
            $this->setSystemSetting('show_demo_instruments', (bool)$payload['show_demo_instruments']);

        if (isset($payload['enable_public_page']))
            $this->setSystemSetting('enable_public_page', (bool)$payload['enable_public_page']);

        if (isset($payload['public_library_title']))
            $this->setSystemSetting('public_library_title', trim((string)$payload['public_library_title']));

        if (isset($payload['public_library_blurb']))
            $this->setSystemSetting('public_library_blurb', trim((string)$payload['public_library_blurb']));

        if (isset($payload['internal_library_title']))
            $this->setSystemSetting('internal_library_title', trim((string)$payload['internal_library_title']));

        if (isset($payload['internal_library_blurb']))
            $this->setSystemSetting('internal_library_blurb', trim((string)$payload['internal_library_blurb']));

        if (isset($payload['categories']) && is_array($payload['categories'])) {
            $cleanCats = array_values(array_filter(array_map('trim', $payload['categories'])));
            $this->setSystemSetting('categories', $cleanCats);
        }

        if (isset($payload['submission_alert_emails']) && is_array($payload['submission_alert_emails'])) {
            $cleanEmails = array_values(array_filter(array_map('trim', $payload['submission_alert_emails'])));
            $this->setSystemSetting('submission_alert_emails', $cleanEmails);
        }

        if (isset($payload['default_decline_email_body'])) {
            $this->setSystemSetting('default_decline_email_body', trim((string)$payload['default_decline_email_body']));
        }

        if (isset($payload['allow_owner_direct_update']))
            $this->setSystemSetting('allow_owner_direct_update', (bool)$payload['allow_owner_direct_update']);

        if (isset($payload['sender_email_address']))
            $this->setSystemSetting('sender_email_address', trim((string)$payload['sender_email_address']));

        if (isset($payload['popular_calculation_method']))
            $this->setSystemSetting('popular_calculation_method', trim((string)$payload['popular_calculation_method']));

        $this->log("Updated system settings", [
            'action' => 'save-settings'
        ]);

        return ['status' => 'success', 'message' => 'System settings saved successfully.'];
    }

    private function handleSaveMetadataAction($payload)
    {
        $id = $payload['id'] ?? '';
        if (empty($id))
            return ['status' => 'error', 'message' => 'Instrument ID is required.'];

        $uploaded = $this->loadUploadedInstruments();
        $found = false;
        foreach ($uploaded as $uIdx => $uInst) {
            if ($uInst['id'] !== $id)
                continue;
            if (isset($payload['title']))       $uploaded[$uIdx]['title']       = trim((string)$payload['title']);
            if (isset($payload['description'])) $uploaded[$uIdx]['description'] = trim((string)$payload['description']);
            if (isset($payload['author_name'])) $uploaded[$uIdx]['author_name'] = trim((string)$payload['author_name']);
            if (isset($payload['author_email'])) $uploaded[$uIdx]['author_email'] = trim((string)$payload['author_email']);
            if (isset($payload['owner']))       $uploaded[$uIdx]['owner']       = trim((string)$payload['owner']);
            if (isset($payload['categories']) && is_array($payload['categories']))
                $uploaded[$uIdx]['categories'] = array_values(array_filter(array_map('trim', $payload['categories'])));
            $found = true;
            break;
        }

        if (!$found)
            return ['status' => 'error', 'message' => 'Instrument not found in active library.'];

        $this->saveUploadedInstruments($uploaded);

        $this->log("Updated instrument metadata", [
            'action'        => 'save-metadata',
            'instrument_id' => $id,
            'title'         => $payload['title'] ?? '',
            'owner'         => $payload['owner'] ?? ''
        ]);

        return ['status' => 'success', 'message' => 'Instrument metadata saved successfully.'];
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
            $var = trim($row[$nameIdx] ?? '');
            $typeField = $row[$typeIdx] ?? '';
            $label = $row[$labelIdx] ?? '';
            if (empty($var))
                continue;
            if (strtolower($var) === 'record_id')
                return ['status' => 'error', 'message' => 'The instrument CSV contains a "record_id" field. Please remove it before submitting.'];
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
            if (strtolower(trim($f['field_name'] ?? '')) === 'record_id')
                continue;
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
        $categories = $payload['categories'] ?? [];
        $submitter = ExternalModules::getUsername();

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

        $pending_submissions = $this->loadPendingSubmissions();

        if ($subType === 'update') {
            $existingId = $payload['existingId'] ?? '';
            $target_inst = null;
            foreach ($this->getInstruments() as $inst) {
                if ($inst['id'] === $existingId) {
                    $target_inst = $inst;
                    break;
                }
            }

            $update_sub = [
                'id'              => $existingId ?: ('upd_' . time()),
                'title'           => !empty($title) ? $title : ($target_inst['title'] ?? 'Updated Instrument'),
                'categories'      => !empty($categories) ? $categories : ($target_inst['categories'] ?? []),
                'description'     => !empty($description) ? $description : ($target_inst['description'] ?? ''),
                'author_name'     => !empty($author_name) ? $author_name : ($target_inst['author_name'] ?? ''),
                'author_email'    => !empty($author_email) ? $author_email : ($target_inst['author_email'] ?? ''),
                'submitted_by'    => $submitter,
                'submitted_date'  => date('Y-m-d'),
                'submission_date' => date('Y-m-d'),
                'owner'           => $target_inst['owner'] ?? $submitter,
                'installs'        => 0,
                'fields'          => $fields,
                'versions'        => [
                    [
                        'version'        => $version,
                        'doc_id'         => $doc_id,
                        'submitted_by'   => $submitter,
                        'submitted_date' => date('Y-m-d')
                    ]
                ]
            ];

            // Owner bypass: if enabled and submitter is the instrument owner, publish directly
            $ownerBypassEnabled = (bool)$this->getSystemSetting('allow_owner_direct_update');
            if ($ownerBypassEnabled && ($target_inst['owner'] ?? '') === $submitter && $target_inst !== null) {
                $uploaded = $this->loadUploadedInstruments();
                foreach ($uploaded as $uIdx => $uInst) {
                    if ($uInst['id'] !== $existingId)
                        continue;
                    $uploaded[$uIdx]['title']       = $update_sub['title'];
                    $uploaded[$uIdx]['categories']  = $update_sub['categories'];
                    $uploaded[$uIdx]['description'] = $update_sub['description'];
                    $uploaded[$uIdx]['author_name'] = $update_sub['author_name'];
                    $uploaded[$uIdx]['author_email'] = $update_sub['author_email'];
                    $uploaded[$uIdx]['fields']      = $update_sub['fields'];
                    $existing_versions = $uploaded[$uIdx]['versions'] ?? [];
                    $vExists = false;
                    foreach ($existing_versions as $eV) {
                        if ($eV['version'] === $version) $vExists = true;
                    }
                    if (!$vExists)
                        $existing_versions[] = $update_sub['versions'][0];
                    $uploaded[$uIdx]['versions'] = $existing_versions;
                    break;
                }
                $this->saveUploadedInstruments($uploaded);
                $this->sendSubmissionAlertEmail($update_sub['title'], $author_name, $author_email, $submitter, $version);
                $this->log("Direct instrument update via owner bypass", [
                    'action'        => 'owner-bypass-update',
                    'instrument_id' => $existingId,
                    'title'         => $update_sub['title'],
                    'version'       => $version,
                    'owner'         => $submitter
                ]);
                return ['status' => 'success', 'message' => 'Update published directly to active library (owner bypass).', 'bypass' => true];
            }

            $pending_submissions[] = $update_sub;
        } else {
            $clean_id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $title));
            $clean_id = preg_replace('/_+/', '_', $clean_id);
            $clean_id = trim($clean_id, '_');

            $all_existing = array_merge($this->getInstruments(), $pending_submissions);
            $id_exists = false;
            foreach ($all_existing as $inst) {
                if ($inst['id'] === $clean_id) $id_exists = true;
            }
            if ($id_exists)
                $clean_id .= '_' . time();

            $new_inst = [
                'id'              => $clean_id,
                'title'           => $title,
                'categories'      => $categories,
                'description'     => $description,
                'author_name'     => $author_name,
                'author_email'    => $author_email,
                'submitted_by'    => $submitter,
                'submitted_date'  => date('Y-m-d'),
                'submission_date' => date('Y-m-d'),
                'owner'           => $submitter,
                'installs'        => 0,
                'fields'          => $fields,
                'versions'        => [
                    [
                        'version'        => $version,
                        'doc_id'         => $doc_id,
                        'submitted_by'   => $submitter,
                        'submitted_date' => date('Y-m-d')
                    ]
                ]
            ];
            $pending_submissions[] = $new_inst;
        }

        $this->savePendingSubmissions($pending_submissions);
        $this->sendSubmissionAlertEmail($title, $author_name, $author_email, ExternalModules::getUsername(), $version);
        return ['status' => 'success', 'message' => 'Instrument successfully submitted for administrator moderation.'];
    }

    private function sendSubmissionAlertEmail($title, $author_name, $author_email, $submitter, $version)
    {
        $emails = $this->getSystemSetting('submission_alert_emails');
        if (empty($emails) || !is_array($emails))
            return;

        $admin_url = $this->getUrl('admin.php');
        if (preg_match('#/redcap_v\d+\.\d+\.\d+/#i', $admin_url)) {
            $admin_url = preg_replace('#/redcap_v\d+\.\d+\.\d+/ExternalModules/\?#i', '/api/?type=module&', $admin_url);
            $admin_url = preg_replace('#/redcap_v\d+\.\d+\.\d+/#i', '/', $admin_url);
        }

        $defaults = $this->getDefaults();
        $template = $defaults['default_settings']['default_submission_alert_email_body'] ?? "";
        $subject = "[Local Instrument Library] New Instrument Submission: " . $title;
        $body = str_replace(
            ['{title}', '{version}', '{author_name}', '{author_email}', '{submitter}', '{date}', '{admin_url}'],
            [$title, $version, $author_name, $author_email, $submitter, date('Y-m-d H:i:s'), $admin_url],
            $template
        );

        $from = $this->getSenderEmailAddress();
        foreach ($emails as $to) {
            $to = trim($to);
            if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL))
                REDCap::email($to, $from, $subject, $body);
        }
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
        $new_fields = [];
        $is_demo = !empty($target_inst['is_demo']);

        if (!$is_demo) {
            $doc_id = null;
            if (!empty($target_inst['versions']) && is_array($target_inst['versions'])) {
                $targetVerClean = ltrim($target_inst['version'] ?? '', 'v');
                foreach ($target_inst['versions'] as $vObj) {
                    $vClean = ltrim($vObj['version'] ?? '', 'v');
                    if ($vClean === $targetVerClean && !empty($vObj['doc_id'])) {
                        $doc_id = $vObj['doc_id'];
                        break;
                    }
                }
                if (!$doc_id) {
                    $lastVer = end($target_inst['versions']);
                    $doc_id = $lastVer['doc_id'] ?? null;
                }
            }

            $csvContent = null;
            if ($doc_id) {
                $temp_zip = Files::copyEdocToTemp($doc_id);
                if ($temp_zip && file_exists($temp_zip)) {
                    $zip = new ZipArchive();
                    if ($zip->open($temp_zip) === true) {
                        $csvContent = $zip->getFromName('instrument.csv');
                        $zip->close();
                    }
                    @unlink($temp_zip);
                }
            }

            if (!empty($csvContent)) {
                $lines = explode("\n", $csvContent);
                $firstLine = true;
                $col_map = [];

                $header_field_map = [
                    "Variable / Field Name" => "field_name",
                    "Form Name" => "form_name",
                    "Section Header" => "section_header",
                    "Field Type" => "field_type",
                    "Field Label" => "field_label",
                    "Choices, Calculations, OR Slider Labels" => "select_choices_or_calculations",
                    "Field Note" => "field_note",
                    "Text Validation Type OR Show Slider Number" => "text_validation_type_or_show_slider_number",
                    "Text Validation Min" => "text_validation_min",
                    "Text Validation Max" => "text_validation_max",
                    "Identifier?" => "identifier",
                    "Branching Logic" => "branching_logic",
                    "Required?" => "required_field",
                    "Custom Alignment" => "custom_alignment",
                    "Question Number" => "question_number",
                    "Matrix Group Name" => "matrix_group_name",
                    "Matrix Ranking?" => "matrix_ranking",
                    "Field Annotation" => "field_annotation"
                ];

                foreach ($lines as $line) {
                    $row = str_getcsv($line);
                    if (empty($row) || count($row) < 2) continue;

                    if ($firstLine) {
                        $headers = array_map('trim', $row);
                        foreach ($header_field_map as $header => $key) {
                            $idx = array_search($header, $headers);
                            $col_map[$key] = ($idx !== false) ? $idx : count($col_map);
                        }
                        $firstLine = false;
                        continue;
                    }

                    $var = trim($row[$col_map['field_name']] ?? '');
                    if (empty($var) || strtolower($var) === 'record_id') continue;

                    $field = [];
                    foreach ($col_map as $key => $idx) {
                        $field[$key] = $row[$idx] ?? '';
                    }
                    if (empty($field['form_name']))
                        $field['form_name'] = $inst_id;

                    $new_fields[] = $field;
                }
            }
        }

        if (empty($new_fields)) {
            $placeholder_field = $inst_id . "_placeholder";
            $new_fields[] = [
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
        }

        foreach ($new_fields as $nf) {
            $current_dd[] = $nf;
        }

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

        REDCap::logEvent(
            "Install instrument from Local Instrument Library",
            "Form Name: {$inst_id}\nTitle: " . ($target_inst['title'] ?? '') . "\nVersion: " . ($target_inst['version'] ?? '1.0.0'),
            "",
            null,
            null,
            $project_id
        );

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

        REDCap::logEvent(
            "Remove instrument tracking from Local Instrument Library",
            "Form Name: {$inst_id}",
            "",
            null,
            null,
            $project_id
        );

        return ['status' => 'success', 'message' => 'Removed instrument'];
    }

    public function loadPendingSubmissions()
    {
        $json = $this->getSystemSetting("pending_submissions_metadata");
        if (empty($json))
            return [];

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function savePendingSubmissions($pending)
    {
        $json = json_encode(array_values($pending));
        $this->setSystemSetting("pending_submissions_metadata", $json);
    }

    public function getPendingSubmissions($includeDemos = null)
    {
        if ($includeDemos === null)
            $includeDemos = (bool)$this->getSystemSetting('show_demo_instruments');

        $real_pending = $this->loadPendingSubmissions();

        $demo_pending = [];
        if ($includeDemos) {
            $defaults = $this->getDefaults();
            $domain = $this->getContactEmailDomain();
            foreach ($defaults['demo_pending_submissions'] ?? [] as $sub) {
                if (!empty($sub['author_email']))
                    $sub['author_email'] = preg_replace('/@(.*)$/', '@' . $domain, $sub['author_email']);
                $sub['is_demo'] = true;
                $demo_pending[] = $sub;
            }
        }

        $combined = array_merge($real_pending, $demo_pending);

        foreach ($combined as &$sub) {
            $latest = "0.0.0";
            if (isset($sub['versions']) && is_array($sub['versions'])) {
                foreach ($sub['versions'] as $vObj) {
                    $v = $vObj['version'];
                    $vClean = ltrim($v, 'v');
                    if (version_compare($vClean, $latest, '>'))
                        $latest = $vClean;
                }
            }
            $sub['version'] = 'v' . $latest;
            $sub['status'] = 'pending';
        }
        unset($sub);

        return $combined;
    }

    private function handleApproveSubmissionAction($payload)
    {
        if (!SUPER_USER)
            return ['status' => 'error', 'message' => 'Only REDCap Administrators can approve submissions.'];

        $id = $payload['id'] ?? '';
        if (empty($id))
            return ['status' => 'error', 'message' => 'Submission ID is required.'];

        $pending = $this->loadPendingSubmissions();
        $target_index = -1;
        $target_sub = null;

        foreach ($pending as $idx => $sub) {
            if ($sub['id'] === $id) {
                $target_index = $idx;
                $target_sub = $sub;
                break;
            }
        }

        if (!$target_sub && (bool)$this->getSystemSetting('show_demo_instruments')) {
            $defaults = $this->getDefaults();
            foreach ($defaults['demo_pending_submissions'] ?? [] as $sub) {
                if ($sub['id'] === $id) {
                    $target_sub = $sub;
                    break;
                }
            }
        }

        if (!$target_sub)
            return ['status' => 'error', 'message' => 'Submission not found.'];

        if ($target_index === -1 || !empty($target_sub['is_demo'])) {
            $this->sendApprovalEmailToSubmitter($target_sub);
            return ['status' => 'success', 'message' => 'Demo submission approved (demo instruments are for preview only and are not saved to active storage).'];
        }

        $uploaded = $this->loadUploadedInstruments();
        $existing_index = -1;
        foreach ($uploaded as $uIdx => $uInst) {
            if ($uInst['id'] === $target_sub['id']) {
                $existing_index = $uIdx;
                break;
            }
        }

        if ($existing_index >= 0) {
            $uploaded[$existing_index]['title'] = $target_sub['title'];
            $uploaded[$existing_index]['categories'] = $target_sub['categories'];
            $uploaded[$existing_index]['description'] = $target_sub['description'];
            $uploaded[$existing_index]['author_name'] = $target_sub['author_name'];
            $uploaded[$existing_index]['author_email'] = $target_sub['author_email'];
            $uploaded[$existing_index]['fields'] = $target_sub['fields'];

            $existing_versions = $uploaded[$existing_index]['versions'] ?? [];
            foreach ($target_sub['versions'] as $newV) {
                $vExists = false;
                foreach ($existing_versions as $eV) {
                    if ($eV['version'] === $newV['version']) $vExists = true;
                }
                if (!$vExists) $existing_versions[] = $newV;
            }
            $uploaded[$existing_index]['versions'] = $existing_versions;
        } else {
            $uploaded[] = $target_sub;
        }

        $this->saveUploadedInstruments($uploaded);

        if ($target_index >= 0) {
            array_splice($pending, $target_index, 1);
            $this->savePendingSubmissions($pending);
        }

        $this->sendApprovalEmailToSubmitter($target_sub);

        $this->log("Approved instrument submission", [
            'action'        => 'approve-submission',
            'instrument_id' => $id,
            'title'         => $target_sub['title'] ?? ''
        ]);

        return ['status' => 'success', 'message' => 'Submission approved and published to active library.'];
    }

    private function handleDeclineSubmissionAction($payload)
    {
        if (!SUPER_USER)
            return ['status' => 'error', 'message' => 'Only REDCap Administrators can decline submissions.'];

        $id = $payload['id'] ?? '';
        $send_email = !empty($payload['send_email']);
        $reason = trim($payload['reason'] ?? '');

        if (empty($id))
            return ['status' => 'error', 'message' => 'Submission ID is required.'];

        $pending = $this->loadPendingSubmissions();
        $target_index = -1;
        $target_sub = null;

        foreach ($pending as $idx => $sub) {
            if ($sub['id'] === $id) {
                $target_index = $idx;
                $target_sub = $sub;
                break;
            }
        }

        if (!$target_sub && (bool)$this->getSystemSetting('show_demo_instruments')) {
            $defaults = $this->getDefaults();
            foreach ($defaults['demo_pending_submissions'] ?? [] as $sub) {
                if ($sub['id'] === $id) {
                    $target_sub = $sub;
                    break;
                }
            }
        }

        if (!$target_sub)
            return ['status' => 'error', 'message' => 'Submission not found.'];

        if ($target_index === -1 || !empty($target_sub['is_demo'])) {
            if ($send_email) {
                $this->sendDeclineEmailToSubmitter($target_sub, $reason);
            }
            return ['status' => 'success', 'message' => 'Demo submission declined (demo instruments are for preview only).'];
        }

        if (isset($target_sub['versions']) && is_array($target_sub['versions'])) {
            foreach ($target_sub['versions'] as $vObj) {
                if (!empty($vObj['doc_id']) && is_numeric($vObj['doc_id'])) {
                    if (class_exists('Files') && method_exists('Files', 'deleteFile')) {
                        Files::deleteFile($vObj['doc_id']);
                    }
                }
            }
        }

        if ($target_index >= 0) {
            array_splice($pending, $target_index, 1);
            $this->savePendingSubmissions($pending);
        }

        if ($send_email) {
            $this->sendDeclineEmailToSubmitter($target_sub, $reason);
        }

        $this->log("Declined instrument submission", [
            'action'        => 'decline-submission',
            'instrument_id' => $id,
            'title'         => $target_sub['title'] ?? '',
            'reason'        => $reason
        ]);

        return ['status' => 'success', 'message' => 'Submission declined and removed.'];
    }

    private function sendApprovalEmailToSubmitter($sub)
    {
        $to = $sub['author_email'] ?? '';
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL))
            return;

        $defaults = $this->getDefaults();
        $template = $defaults['default_settings']['default_approval_email_body'] ?? "";
        $subject = "[Local Instrument Library] Submission Approved: " . $sub['title'];
        $greeting = "Hello " . ($sub['author_name'] ?: 'Submitter') . ",\n\n";
        $body = $greeting . str_replace('{title}', $sub['title'], $template);

        $from = $this->getSenderEmailAddress();
        REDCap::email($to, $from, $subject, $body);
    }

    private function sendDeclineEmailToSubmitter($sub, $reason)
    {
        $to = $sub['author_email'] ?? '';
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL))
            return;

        $defaults = $this->getDefaults();
        $template = (string)$this->getSystemSetting('default_decline_email_body');
        if (empty($template)) {
            $template = (string)($defaults['default_settings']['default_decline_email_body'] ?? '');
        }

        $reasonText = !empty($reason) ? $reason : "No specific reason provided.";

        $subject = "[Local Instrument Library] Submission Update: " . $sub['title'];
        $greeting = "Hello " . ($sub['author_name'] ?: 'Submitter') . ",\n\n";
        $body = $greeting . str_replace(
            ['{title}', '{reason}'],
            [$sub['title'], $reasonText],
            $template
        );

        $from = $this->getSenderEmailAddress();
        REDCap::email($to, $from, $subject, $body);
    }

    public function getSenderEmailAddress()
    {
        $configured = (string)$this->getSystemSetting('sender_email_address');
        $configured = trim($configured);
        if (!empty($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL))
            return $configured;

        $defaultAdmin = trim($GLOBALS['homepage_contact_email']);
        if (!empty($defaultAdmin) && filter_var($defaultAdmin, FILTER_VALIDATE_EMAIL))
            return $defaultAdmin;

        return 'redcap@' . $this->getContactEmailDomain();
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
                $inst['is_demo'] = true;
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
        return json_decode(file_get_contents($path), true);
    }

    public function getContactEmailDomain()
    {
        $sql = $this->query("SELECT value FROM redcap_config WHERE field_name = 'homepage_contact_email'", []);
        $email = db_fetch_assoc($sql)['value'] ?? '';
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            return $this->escape($parts[1]);
        }
        $defaults = $this->getDefaults();
        return $this->escape($defaults['default_settings']['default_contact_email_domain'] ?? 'example.edu');
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

    public function getCategoriesList()
    {
        $configured = $this->getSystemSetting('categories');
        $raw = [];
        $defaults = $this->getDefaults();
        $default_cats = $defaults['default_categories'] ?? [];

        if (empty($configured)) {
            foreach ($default_cats as $catName) {
                $raw[] = ["name" => $catName];
            }
        } else {
            foreach ($configured as $catName) {
                $name = trim($catName);
                if (!empty($name))
                    $raw[] = ["name" => $name];
            }
        }

        $nice_colors = $defaults['category_colors'] ?? [];
        if (empty($nice_colors)) {
            throw new Exception("Category color definitions missing in defaults.json.");
        }

        $categories_list = [];
        $color_count = count($nice_colors);
        foreach ($raw as $index => $cat) {
            $color_index = $color_count > 0 ? ($index % $color_count) : 0;
            $color = $nice_colors[$color_index];
            $name = $cat['name'];

            $categories_list[] = [
                "name" => $name,
                "class" => "category-" . strtolower(str_replace(' ', '', $name)),
                "bg" => $color['bg'],
                "text" => $color['text']
            ];
        }
        return $categories_list;
    }

    public function renderCategoryCSS(array $categories_list, array $scope_selectors)
    {
        return implode(array_map(function ($cat) use ($scope_selectors) {
            $scoped = implode(",\n", array_map(function ($sel) use ($cat) {
                return $sel . " ." . $cat['class'];
            }, $scope_selectors));
            return sprintf(
                "%s {\n    background-color: %s !important;\n    color: %s !important;\n    border-color: %s !important;\n}\n\n",
                $scoped,
                $cat['bg'],
                $cat['text'],
                $cat['bg']
            );
        }, $categories_list));
    }

    public function renderFieldTypeCSS(array $instruments, array $scope_selectors)
    {
        $defaults = $this->getDefaults();
        $field_type_colors = $defaults['field_type_colors'] ?? [];
        $unique_types = [];

        foreach ($instruments as $inst) {
            if (isset($inst['fields']) && is_array($inst['fields'])) {
                foreach ($inst['fields'] as $field) {
                    $type = strtolower(trim($field['type'] ?? ''));
                    $cleanType = str_replace(['_', ' '], '', $type);
                    if (!empty($cleanType))
                        $unique_types[$cleanType] = $cleanType;
                }
            }
        }

        $dynamic_styles = "";
        $default_colors = $field_type_colors['default'] ?? ["bg" => "#f1f5f9", "text" => "#475569", "border" => "#cbd5e1"];

        foreach ($unique_types as $cleanType) {
            $colors = $field_type_colors[$cleanType] ?? $default_colors;
            $scoped = implode(",\n", array_map(function ($sel) use ($cleanType) {
                return $sel . " .type-" . $cleanType;
            }, $scope_selectors));

            $dynamic_styles .= sprintf(
                "%s {\n    background-color: %s !important;\n    color: %s !important;\n    border-color: %s !important;\n    font-weight: 600 !important;\n}\n\n",
                $scoped,
                $colors['bg'],
                $colors['text'],
                $colors['border']
            );
        }
        return $dynamic_styles;
    }
}
