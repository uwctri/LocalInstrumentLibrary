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
            $this->setSystemSetting("storage_project", $this->createProject("Local Instrument Library", 1, "Stores local instruments for easy sharing"));
        }
        $defaults = $this->getDefaults();
        foreach ($defaults['default_settings'] ?? [] as $key => $default_value) {
            $val = $this->getSystemSetting($key);
            if ($val === null || $val === "") $this->setSystemSetting($key, $default_value);
        }
    }

    public function redcap_every_page_top($project_id)
    {
        if (!$this->isPage('Design/online_designer.php') || empty($project_id)) return;
        $library_url = $this->getUrl('index.php');
?>
        <script type="text/javascript">
            $(document).ready(() => {
                let button = $('button:contains("Upload")');
                if (button.length === 0) return;
                let newButton = button.clone().attr({ id: 'local-library-btn', href: '<?= $library_url; ?>', style: '' }).html('<i class="fas fa-book-open"></i> Local Instrument Library').removeAttr('onclick');
                let newContainer = button.closest('div').clone().html(newButton[0].outerHTML);
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

        $actions = [
            'submit'             => fn() => $this->handleSubmitAction($payload, $project_id),
            'install'            => fn() => $this->handleInstallAction($payload, $project_id),
            'uninstall'          => fn() => $this->handleUninstallAction($payload, $project_id),
            'save-settings'      => fn() => $this->handleSaveSettingsAction($payload),
            'approve-submission' => fn() => $this->handleApproveSubmissionAction($payload),
            'decline-submission' => fn() => $this->handleDeclineSubmissionAction($payload),
            'save-metadata'      => fn() => $this->handleSaveMetadataAction($payload),
        ];

        return isset($actions[$action]) ? $actions[$action]() : ['status' => 'error', 'message' => 'Invalid action.'];
    }

    private function handleSaveSettingsAction($payload)
    {
        $settingsMap = [
            'show_demo_instruments'     => ['type' => 'bool', 'target' => 'show_demo_instruments'],
            'enable_public_page'        => ['type' => 'bool', 'target' => 'enable_public_page'],
            'enable_project_by_default' => ['type' => 'bool', 'target' => 'enabled'],
            'hide_from_non_admins'      => ['type' => 'bool', 'target' => 'reserved-hide-from-non-admins-in-project-list'],
            'public_library_title'      => ['type' => 'string', 'target' => 'public_library_title'],
            'public_library_blurb'      => ['type' => 'string', 'target' => 'public_library_blurb'],
            'internal_library_title'    => ['type' => 'string', 'target' => 'internal_library_title'],
            'internal_library_blurb'    => ['type' => 'string', 'target' => 'internal_library_blurb'],
            'categories'                => ['type' => 'array', 'target' => 'categories'],
            'submission_alert_emails'   => ['type' => 'array', 'target' => 'submission_alert_emails'],
            'default_decline_email_body'=> ['type' => 'string', 'target' => 'default_decline_email_body'],
            'allow_owner_direct_update' => ['type' => 'bool', 'target' => 'allow_owner_direct_update'],
            'sender_email_address'      => ['type' => 'string', 'target' => 'sender_email_address'],
            'popular_calculation_method' => ['type' => 'string', 'target' => 'popular_calculation_method'],
            'trusted_users'             => ['type' => 'array', 'target' => 'trusted_users'],
        ];

        foreach ($settingsMap as $key => $spec) {
            if (!isset($payload[$key])) continue;
            $val = $payload[$key];
            $target = $spec['target'];

            if ($spec['type'] === 'bool') $this->setSystemSetting($target, (bool)$val);
            elseif ($spec['type'] === 'string') $this->setSystemSetting($target, trim((string)$val));
            elseif ($spec['type'] === 'array' && is_array($val)) $this->setSystemSetting($target, array_values(array_filter(array_map('trim', $val))));
        }

        $this->log("Updated system settings", ['action' => 'save-settings']);
        return ['status' => 'success', 'message' => 'System settings saved successfully.'];
    }

    private function handleSaveMetadataAction($payload)
    {
        $id = $payload['id'] ?? '';
        if (empty($id)) return ['status' => 'error', 'message' => 'Instrument ID is required.'];

        $uploaded = $this->loadUploadedInstruments();
        $found = false;
        foreach ($uploaded as $uIdx => $uInst) {
            if ($uInst['id'] !== $id) continue;
            if (isset($payload['title'])) $uploaded[$uIdx]['title'] = trim((string)$payload['title']);
            if (isset($payload['description'])) $uploaded[$uIdx]['description'] = trim((string)$payload['description']);
            if (isset($payload['author_name'])) $uploaded[$uIdx]['author_name'] = trim((string)$payload['author_name']);
            if (isset($payload['author_email'])) $uploaded[$uIdx]['author_email'] = trim((string)$payload['author_email']);
            if (isset($payload['owner'])) $uploaded[$uIdx]['owner'] = trim((string)$payload['owner']);
            if (isset($payload['status'])) $uploaded[$uIdx]['status'] = trim((string)$payload['status']);
            if (isset($payload['categories']) && is_array($payload['categories'])) {
                $uploaded[$uIdx]['categories'] = array_values(array_filter(array_map('trim', $payload['categories'])));
            }
            $found = true;
            break;
        }

        if (!$found) return ['status' => 'error', 'message' => 'Instrument not found in active library.'];
        $this->saveUploadedInstruments($uploaded);

        $this->log("Updated instrument metadata", [
            'action' => 'save-metadata',
            'instrument_id' => $id,
            'title' => $payload['title'] ?? '',
            'owner' => $payload['owner'] ?? '',
            'status' => $payload['status'] ?? ''
        ]);
        return ['status' => 'success', 'message' => 'Instrument metadata saved successfully.'];
    }

    private function handleZipSubmission($payload)
    {
        $fileData = base64_decode($payload['fileData'] ?? '');
        if (empty($fileData)) return ['status' => 'error', 'message' => 'No file data received.'];
        $tempFile = tempnam(sys_get_temp_dir(), 'libzip');
        file_put_contents($tempFile, $fileData);

        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Failed to open ZIP archive.'];
        }
        if ($zip->locateName('instrument.csv') === false || $zip->locateName('OriginID.txt') === false) {
            $zip->close();
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'ZIP archive must contain instrument.csv and OriginID.txt at the root.'];
        }

        $csvContent = $zip->getFromName('instrument.csv');
        $zip->close();

        $lines = explode("\n", $csvContent);
        $firstLine = true;
        $nameIdx = 0;
        $typeIdx = 3;
        $labelIdx = 4;
        $fields = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line, ",", '"', "\\");
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
            if (empty($var)) continue;
            if (strtolower($var) === 'record_id') {
                unlink($tempFile);
                return ['status' => 'error', 'message' => 'The instrument CSV contains a "record_id" field. Please remove it before submitting.'];
            }
            $fields[] = ['label' => $row[$labelIdx] ?? '', 'variable' => $var, 'type' => $row[$typeIdx] ?? ''];
        }

        $storage_pid = $this->getSystemSetting("storage_project");
        if (empty($storage_pid)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Storage project not configured. Please enable the module first.'];
        }

        $originalName = $payload['fileName'] ?? 'instrument.zip';
        $fileName = pathinfo($originalName, PATHINFO_FILENAME) . "_" . ($payload['version'] ?? '1.0.0') . "." . pathinfo($originalName, PATHINFO_EXTENSION);

        $doc_id = Files::uploadFile(['name' => $fileName, 'tmp_name' => $tempFile, 'size' => filesize($tempFile)], $storage_pid);
        if (empty($doc_id)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Failed to save ZIP file to storage project.'];
        }

        REDCap::addFileToRepository($doc_id, $storage_pid, "Uploaded from Instrument Library by " . ExternalModules::getUsername());
        return ['status' => 'success', 'fields' => $fields, 'doc_id' => $doc_id];
    }

    private function handleProjectSubmission($payload, $project_id)
    {
        $instName = $payload['instrumentName'] ?? '';
        if (empty($instName)) return ['status' => 'error', 'message' => 'No instrument selected.'];
        $metadata = $this->getMetadata($project_id, $instName);
        if (empty($metadata)) return ['status' => 'error', 'message' => 'Instrument not found or has no fields.'];

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ["Variable / Field Name", "Form Name", "Section Header", "Field Type", "Field Label", "Choices, Calculations, OR Slider Labels", "Field Note", "Text Validation Type OR Show Slider Number", "Text Validation Min", "Text Validation Max", "Identifier?", "Branching Logic", "Required?", "Custom Alignment", "Question Number", "Matrix Group Name", "Matrix Ranking?", "Field Annotation"], ",", '"', "\\");

        $fields = [];
        foreach ($metadata as $f) {
            if (strtolower(trim($f['field_name'] ?? '')) === 'record_id') continue;
            fputcsv($fp, [
                $f['field_name'] ?? '', $f['form_name'] ?? '', $f['section_header'] ?? '', $f['field_type'] ?? '',
                $f['field_label'] ?? '', $f['select_choices_or_calculations'] ?? '', $f['field_note'] ?? '',
                $f['text_validation_type_or_show_slider_number'] ?? '', $f['text_validation_min'] ?? '',
                $f['text_validation_max'] ?? '', $f['identifier'] ?? '', $f['branching_logic'] ?? '',
                $f['required_field'] ?? '', $f['custom_alignment'] ?? '', $f['question_number'] ?? '',
                $f['matrix_group_name'] ?? '', $f['matrix_ranking'] ?? '', $f['field_annotation'] ?? ''
            ], ",", '"', "\\");
            $fields[] = ['label' => $f['field_label'] ?? '', 'variable' => $f['field_name'] ?? '', 'type' => $f['field_type'] ?? ''];
        }
        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        $tempFile = tempnam(sys_get_temp_dir(), 'projzip');
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return ['status' => 'error', 'message' => 'Failed to create ZIP package.'];
        $zip->addFromString('instrument.csv', $csvContent);
        $zip->addFromString('OriginID.txt', APP_PATH_WEBROOT_FULL . "index.php?pid=" . $project_id);
        $zip->close();

        $storage_pid = $this->getSystemSetting("storage_project");
        if (empty($storage_pid)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Storage project not configured. Please enable the module first.'];
        }

        $doc_id = Files::uploadFile(['name' => $instName . "_instrument_" . ($payload['version'] ?? '1.0.0') . ".zip", 'tmp_name' => $tempFile, 'size' => filesize($tempFile)], $storage_pid);
        if (empty($doc_id)) {
            unlink($tempFile);
            return ['status' => 'error', 'message' => 'Failed to save ZIP file to storage project.'];
        }

        REDCap::addFileToRepository($doc_id, $storage_pid, "Uploaded from Instrument Library by " . ExternalModules::getUsername());
        return ['status' => 'success', 'fields' => $fields, 'doc_id' => $doc_id];
    }

    public function isTrustedUser($username)
    {
        if (empty($username)) return false;
        if (defined('SUPER_USER') && SUPER_USER) return true;
        $trusted = $this->getSystemSetting('trusted_users') ?: [];
        if (!is_array($trusted)) return false;
        return in_array(strtolower(trim($username)), array_map('strtolower', array_map('trim', $trusted)));
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

        if ($subType === 'new' && empty($title)) return ['status' => 'error', 'message' => 'Instrument title is required.'];

        if ($type === 'zip') $result = $this->handleZipSubmission($payload);
        elseif ($type === 'project') $result = $this->handleProjectSubmission($payload, $project_id);
        else return ['status' => 'error', 'message' => 'Invalid submission type.'];

        if ($result['status'] === 'error') return $result;

        $fields = $result['fields'];
        $doc_id = $result['doc_id'];
        $pending_submissions = $this->loadPendingSubmissions();

        if ($subType === 'update') {
            $existingId = $payload['existingId'] ?? '';
            $target_inst = null;
            foreach ($this->getInstruments(null, true) as $inst) {
                if ($inst['id'] === $existingId) {
                    $target_inst = $inst;
                    break;
                }
            }

            $update_sub = [
                'id' => $existingId ?: ('upd_' . time()),
                'title' => !empty($title) ? $title : ($target_inst['title'] ?? 'Updated Instrument'),
                'categories' => !empty($categories) ? $categories : ($target_inst['categories'] ?? []),
                'description' => !empty($description) ? $description : ($target_inst['description'] ?? ''),
                'author_name' => !empty($author_name) ? $author_name : ($target_inst['author_name'] ?? ''),
                'author_email' => !empty($author_email) ? $author_email : ($target_inst['author_email'] ?? ''),
                'submitted_by' => $submitter, 'submitted_date' => date('Y-m-d'), 'submission_date' => date('Y-m-d'),
                'owner' => $target_inst['owner'] ?? $submitter, 'status' => $target_inst['status'] ?? 'active', 'installs' => 0, 'fields' => $fields,
                'versions' => [['version' => $version, 'doc_id' => $doc_id, 'submitted_by' => $submitter, 'submitted_date' => date('Y-m-d')]]
            ];

            $is_trusted = $this->isTrustedUser($submitter);
            $is_owner = ($target_inst['owner'] ?? '') === $submitter;
            $allow_owner_bypass = (bool)$this->getSystemSetting('allow_owner_direct_update');

            if (($is_trusted || ($allow_owner_bypass && $is_owner)) && $target_inst !== null) {
                $uploaded = $this->loadUploadedInstruments();
                foreach ($uploaded as $uIdx => $uInst) {
                    if ($uInst['id'] !== $existingId) continue;
                    $uploaded[$uIdx]['title'] = $update_sub['title'];
                    $uploaded[$uIdx]['categories'] = $update_sub['categories'];
                    $uploaded[$uIdx]['description'] = $update_sub['description'];
                    $uploaded[$uIdx]['author_name'] = $update_sub['author_name'];
                    $uploaded[$uIdx]['author_email'] = $update_sub['author_email'];
                    $uploaded[$uIdx]['fields'] = $update_sub['fields'];
                    $existing_versions = $uploaded[$uIdx]['versions'] ?? [];
                    $vExists = false;
                    foreach ($existing_versions as $eV) {
                        if ($eV['version'] === $version) $vExists = true;
                    }
                    if (!$vExists) $existing_versions[] = $update_sub['versions'][0];
                    $uploaded[$uIdx]['versions'] = $existing_versions;
                    break;
                }
                $this->saveUploadedInstruments($uploaded);
                $this->sendSubmissionAlertEmail($update_sub['title'], $author_name, $author_email, $submitter, $version);
                $this->log("Direct instrument update published via trusted/owner bypass", ['action' => 'trusted-bypass-update', 'instrument_id' => $existingId, 'title' => $update_sub['title'], 'version' => $version, 'owner' => $submitter]);
                return ['status' => 'success', 'message' => 'Update published directly to active library.', 'bypass' => true];
            }

            $pending_submissions[] = $update_sub;
        } else {
            $clean_id = trim(preg_replace('/_+/', '_', strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $title))), '_');
            $id_exists = false;
            foreach (array_merge($this->getInstruments(null, true), $pending_submissions) as $inst) {
                if ($inst['id'] === $clean_id) $id_exists = true;
            }
            if ($id_exists) $clean_id .= '_' . time();

            $new_sub = [
                'id' => $clean_id, 'title' => $title, 'categories' => $categories, 'description' => $description,
                'author_name' => $author_name, 'author_email' => $author_email, 'submitted_by' => $submitter,
                'submitted_date' => date('Y-m-d'), 'submission_date' => date('Y-m-d'), 'owner' => $submitter,
                'status' => 'active', 'installs' => 0, 'fields' => $fields,
                'versions' => [['version' => $version, 'doc_id' => $doc_id, 'submitted_by' => $submitter, 'submitted_date' => date('Y-m-d')]]
            ];

            if ($this->isTrustedUser($submitter)) {
                $uploaded = $this->loadUploadedInstruments();
                $uploaded[] = $new_sub;
                $this->saveUploadedInstruments($uploaded);
                $this->sendSubmissionAlertEmail($title, $author_name, $author_email, $submitter, $version);
                $this->log("Direct new instrument published via trusted user bypass", ['action' => 'trusted-bypass-new', 'instrument_id' => $clean_id, 'title' => $title, 'version' => $version, 'owner' => $submitter]);
                return ['status' => 'success', 'message' => 'New instrument published directly to active library.', 'bypass' => true];
            }

            $pending_submissions[] = $new_sub;
        }

        $this->savePendingSubmissions($pending_submissions);
        $this->sendSubmissionAlertEmail($title, $author_name, $author_email, ExternalModules::getUsername(), $version);
        return ['status' => 'success', 'message' => 'Instrument successfully submitted for administrator moderation.'];
    }

    private function sendSubmissionAlertEmail($title, $author_name, $author_email, $submitter, $version)
    {
        $emails = $this->getSystemSetting('submission_alert_emails');
        if (empty($emails) || !is_array($emails)) return;

        $admin_url = $this->getUrl('admin.php');
        if (preg_match('#/redcap_v\d+\.\d+\.\d+/#i', $admin_url)) {
            $admin_url = preg_replace('#/redcap_v\d+\.\d+\.\d+/ExternalModules/\?#i', '/api/?type=module&', $admin_url);
            $admin_url = preg_replace('#/redcap_v\d+\.\d+\.\d+/#i', '/', $admin_url);
        }

        $defaults = $this->getDefaults();
        $template = $defaults['default_settings']['default_submission_alert_email_body'] ?? "";
        $body = str_replace(['{title}', '{version}', '{author_name}', '{author_email}', '{submitter}', '{date}', '{admin_url}'], [$title, $version, $author_name, $author_email, $submitter, date('Y-m-d H:i:s'), $admin_url], $template);
        $from = $this->getSenderEmailAddress();
        foreach ($emails as $to) {
            $to = trim($to);
            if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) REDCap::email($to, $from, "[Local Instrument Library] New Instrument Submission: " . $title, $body);
        }
    }

    private function handleInstallAction($payload, $project_id)
    {
        $inst_id = $payload['id'] ?? null;
        if (empty($inst_id)) return ['status' => 'error', 'message' => 'Missing instrument ID.'];

        $target_inst = null;
        foreach ($this->getInstruments(null, true) as $inst) {
            if ($inst['id'] === $inst_id) {
                $target_inst = $inst;
                break;
            }
        }
        if (!$target_inst) return ['status' => 'error', 'message' => 'Instrument not found in library.'];

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
        if (isset($all_instruments[$inst_id])) $errors[] = "An instrument named '$inst_id' already exists in this project.";

        $overlapping = array_filter(array_column($target_inst['fields'], 'variable'), fn($v) => in_array($v, $all_fields));
        if (!empty($overlapping)) $errors[] = "The following field names already exist in this project: " . implode(', ', $overlapping) . ".";
        if (!empty($errors)) return ['status' => 'error', 'message' => "Cannot install. " . implode(' ', $errors)];

        $current_dd = $this->getMetadata($project_id);
        $new_fields = [];

        if (empty($target_inst['is_demo'])) {
            $doc_id = null;
            if (!empty($target_inst['versions']) && is_array($target_inst['versions'])) {
                $targetVerClean = ltrim($target_inst['version'] ?? '', 'v');
                foreach ($target_inst['versions'] as $vObj) {
                    if (ltrim($vObj['version'] ?? '', 'v') === $targetVerClean && !empty($vObj['doc_id'])) {
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
            if ($doc_id && ($temp_zip = Files::copyEdocToTemp($doc_id))) {
                $zip = new ZipArchive();
                if ($zip->open($temp_zip) === true) {
                    $csvContent = $zip->getFromName('instrument.csv');
                    $zip->close();
                }
                @unlink($temp_zip);
            }

            if (!empty($csvContent)) {
                $lines = explode("\n", $csvContent);
                $firstLine = true;
                $col_map = [];
                $header_field_map = [
                    "Variable / Field Name" => "field_name", "Form Name" => "form_name", "Section Header" => "section_header",
                    "Field Type" => "field_type", "Field Label" => "field_label", "Choices, Calculations, OR Slider Labels" => "select_choices_or_calculations",
                    "Field Note" => "field_note", "Text Validation Type OR Show Slider Number" => "text_validation_type_or_show_slider_number",
                    "Text Validation Min" => "text_validation_min", "Text Validation Max" => "text_validation_max", "Identifier?" => "identifier",
                    "Branching Logic" => "branching_logic", "Required?" => "required_field", "Custom Alignment" => "custom_alignment",
                    "Question Number" => "question_number", "Matrix Group Name" => "matrix_group_name", "Matrix Ranking?" => "matrix_ranking", "Field Annotation" => "field_annotation"
                ];

                foreach ($lines as $line) {
                    $row = str_getcsv($line, ",", '"', "\\");
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
                    foreach ($col_map as $key => $idx) $field[$key] = $row[$idx] ?? '';
                    if (empty($field['form_name'])) $field['form_name'] = $inst_id;
                    $new_fields[] = $field;
                }
            }
        }

        if (empty($new_fields)) {
            $new_fields[] = [
                "field_name" => $inst_id . "_placeholder", "form_name" => $inst_id, "section_header" => "", "field_type" => "descriptive",
                "field_label" => "Start of instrument: " . $target_inst['title'], "select_choices_or_calculations" => "", "field_note" => "",
                "text_validation_type_or_show_slider_number" => "", "text_validation_min" => "", "text_validation_max" => "", "identifier" => "",
                "branching_logic" => "", "required_field" => "", "custom_alignment" => "", "question_number" => "", "matrix_group_name" => "",
                "matrix_ranking" => "", "field_annotation" => ""
            ];
        }

        foreach ($new_fields as $nf) $current_dd[] = $nf;

        list($fieldCount, $saveErrors) = MetaData::saveMetadataFlat($current_dd);
        if (!empty($saveErrors)) return ['status' => 'error', 'message' => 'Failed to save metadata: ' . implode(' ', (array)$saveErrors)];

        $installed = $this->getProjectSetting('installed_instruments', $project_id) ?? [];
        if (!in_array($inst_id, $installed)) {
            $installed[] = $inst_id;
            $this->setProjectSetting('installed_instruments', $installed, $project_id);
        }

        REDCap::logEvent("Install instrument from Local Instrument Library", "Form Name: {$inst_id}\nTitle: " . ($target_inst['title'] ?? '') . "\nVersion: " . ($target_inst['version'] ?? '1.0.0'), "", null, null, $project_id);
        return ['status' => 'success', 'installed' => $installed];
    }

    private function handleUninstallAction($payload, $project_id)
    {
        $inst_id = $payload['id'] ?? null;
        if (empty($inst_id)) return ['status' => 'error', 'message' => 'Missing instrument ID.'];

        $installed = $this->getProjectSetting('installed_instruments', $project_id) ?? [];
        if (($key = array_search($inst_id, $installed)) !== false) {
            unset($installed[$key]);
            $installed = array_values($installed);
            $this->setProjectSetting('installed_instruments', $installed, $project_id);
        }

        REDCap::logEvent("Remove instrument tracking from Local Instrument Library", "Form Name: {$inst_id}", "", null, null, $project_id);
        return ['status' => 'success', 'message' => 'Removed instrument'];
    }

    public function loadPendingSubmissions()
    {
        $json = $this->getSystemSetting("pending_submissions_metadata");
        if (empty($json)) return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function savePendingSubmissions($pending)
    {
        $this->setSystemSetting("pending_submissions_metadata", json_encode(array_values($pending)));
    }

    public function getPendingSubmissions($includeDemos = null)
    {
        if ($includeDemos === null) $includeDemos = (bool)$this->getSystemSetting('show_demo_instruments');
        $real_pending = $this->loadPendingSubmissions();
        $demo_pending = [];

        if ($includeDemos) {
            $defaults = $this->getDefaults();
            $domain = $this->getContactEmailDomain();
            foreach ($defaults['demo_pending_submissions'] ?? [] as $sub) {
                if (!empty($sub['author_email'])) $sub['author_email'] = preg_replace('/@(.*)$/', '@' . $domain, $sub['author_email']);
                $sub['is_demo'] = true;
                $demo_pending[] = $sub;
            }
        }

        $combined = array_merge($real_pending, $demo_pending);

        foreach ($combined as &$sub) {
            $latest = "0.0.0";
            if (isset($sub['versions']) && is_array($sub['versions'])) {
                foreach ($sub['versions'] as $vObj) {
                    $vClean = ltrim($vObj['version'] ?? '', 'v');
                    if (version_compare($vClean, $latest, '>')) $latest = $vClean;
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
        $id = $payload['id'] ?? '';
        if (empty($id)) return ['status' => 'error', 'message' => 'Submission ID is required.'];

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
            foreach (($this->getDefaults()['demo_pending_submissions'] ?? []) as $sub) {
                if ($sub['id'] === $id) {
                    $target_sub = $sub;
                    break;
                }
            }
        }

        if (!$target_sub) return ['status' => 'error', 'message' => 'Submission not found.'];

        if ($target_index === -1 || !empty($target_sub['is_demo'])) {
            $this->sendApprovalEmailToSubmitter($target_sub);
            return ['status' => 'success', 'message' => 'Demo submission approved (demo instruments are for preview only).'];
        }

        $uploaded = $this->loadUploadedInstruments();
        $existing_index = -1;
        foreach ($uploaded as $uIdx => $uInst) {
            if ($uInst['id'] === $target_sub['id']) {
                $existing_index = $uIdx;
                break;
            }
        }

        $target_sub['status'] = 'active';

        if ($existing_index >= 0) {
            $uploaded[$existing_index]['title'] = $target_sub['title'];
            $uploaded[$existing_index]['categories'] = $target_sub['categories'];
            $uploaded[$existing_index]['description'] = $target_sub['description'];
            $uploaded[$existing_index]['author_name'] = $target_sub['author_name'];
            $uploaded[$existing_index]['author_email'] = $target_sub['author_email'];
            $uploaded[$existing_index]['fields'] = $target_sub['fields'];
            $uploaded[$existing_index]['status'] = 'active';
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
        $this->log("Approved instrument submission", ['action' => 'approve-submission', 'instrument_id' => $id, 'title' => $target_sub['title'] ?? '']);
        return ['status' => 'success', 'message' => 'Submission approved and published to active library.'];
    }

    private function handleDeclineSubmissionAction($payload)
    {
        $id = $payload['id'] ?? '';
        if (empty($id)) return ['status' => 'error', 'message' => 'Submission ID is required.'];

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
            foreach (($this->getDefaults()['demo_pending_submissions'] ?? []) as $sub) {
                if ($sub['id'] === $id) {
                    $target_sub = $sub;
                    break;
                }
            }
        }

        if (!$target_sub) return ['status' => 'error', 'message' => 'Submission not found.'];

        if ($target_index === -1 || !empty($target_sub['is_demo'])) {
            if (!empty($payload['send_email'])) $this->sendDeclineEmailToSubmitter($target_sub, trim($payload['reason'] ?? ''));
            return ['status' => 'success', 'message' => 'Demo submission declined.'];
        }

        if (isset($target_sub['versions']) && is_array($target_sub['versions'])) {
            foreach ($target_sub['versions'] as $vObj) {
                if (!empty($vObj['doc_id']) && is_numeric($vObj['doc_id']) && class_exists('Files') && method_exists('Files', 'deleteFile')) {
                    Files::deleteFile($vObj['doc_id']);
                }
            }
        }

        if ($target_index >= 0) {
            array_splice($pending, $target_index, 1);
            $this->savePendingSubmissions($pending);
        }

        if (!empty($payload['send_email'])) $this->sendDeclineEmailToSubmitter($target_sub, trim($payload['reason'] ?? ''));
        $this->log("Declined instrument submission", ['action' => 'decline-submission', 'instrument_id' => $id, 'title' => $target_sub['title'] ?? '', 'reason' => trim($payload['reason'] ?? '')]);
        return ['status' => 'success', 'message' => 'Submission declined and removed.'];
    }

    private function sendApprovalEmailToSubmitter($sub)
    {
        $to = $sub['author_email'] ?? '';
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $defaults = $this->getDefaults();
        $template = $defaults['default_settings']['default_approval_email_body'] ?? "";
        $body = "Hello " . ($sub['author_name'] ?: 'Submitter') . ",\n\n" . str_replace('{title}', $sub['title'], $template);
        REDCap::email($to, $this->getSenderEmailAddress(), "[Local Instrument Library] Submission Approved: " . $sub['title'], $body);
    }

    private function sendDeclineEmailToSubmitter($sub, $reason)
    {
        $to = $sub['author_email'] ?? '';
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $template = (string)$this->getSystemSetting('default_decline_email_body');
        if (empty($template)) $template = (string)($this->getDefaults()['default_settings']['default_decline_email_body'] ?? '');
        $body = "Hello " . ($sub['author_name'] ?: 'Submitter') . ",\n\n" . str_replace(['{title}', '{reason}'], [$sub['title'], !empty($reason) ? $reason : "No specific reason provided."], $template);
        REDCap::email($to, $this->getSenderEmailAddress(), "[Local Instrument Library] Submission Update: " . $sub['title'], $body);
    }

    public function getSenderEmailAddress()
    {
        $configured = trim((string)$this->getSystemSetting('sender_email_address'));
        if (!empty($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL)) return $configured;
        $defaultAdmin = trim($GLOBALS['homepage_contact_email']);
        if (!empty($defaultAdmin) && filter_var($defaultAdmin, FILTER_VALIDATE_EMAIL)) return $defaultAdmin;
        return 'redcap@' . $this->getContactEmailDomain();
    }

    public function getInstruments($includeDemos = null, $includeHidden = false)
    {
        if ($includeDemos === null) $includeDemos = (bool)$this->getSystemSetting('show_demo_instruments');
        $combined = [];

        if ($includeDemos) {
            $domain = $this->getContactEmailDomain();
            foreach (($this->getDefaults()['demo_instruments'] ?? []) as $inst) {
                if (!empty($inst['author_email'])) $inst['author_email'] = preg_replace('/@(.*)$/', '@' . $domain, $inst['author_email']);
                $inst['is_demo'] = true;
                $combined[$inst['id']] = $inst;
            }
        }

        foreach ($this->loadUploadedInstruments() as $inst) $combined[$inst['id']] = $inst;

        foreach ($combined as &$inst) {
            $latest = "0.0.0";
            if (isset($inst['versions']) && is_array($inst['versions'])) {
                foreach ($inst['versions'] as $vObj) {
                    $vClean = ltrim($vObj['version'] ?? '', 'v');
                    if (version_compare($vClean, $latest, '>')) $latest = $vClean;
                }
            }
            $inst['version'] = 'v' . $latest;
            if (!isset($inst['status'])) $inst['status'] = 'active';
        }
        unset($inst);

        if (!$includeHidden) 
            $combined = array_filter($combined, fn($i) => ($i['status'] ?? 'active') !== 'hidden');

        return array_values($combined);
    }

    public function getDefaults()
    {
        return json_decode(file_get_contents(__DIR__ . '/defaults.json'), true);
    }

    public function getContactEmailDomain()
    {
        $sql = $this->query("SELECT value FROM redcap_config WHERE field_name = 'homepage_contact_email'", []);
        $email = db_fetch_assoc($sql)['value'] ?? '';
        $parts = explode('@', $email);
        if (count($parts) === 2) return $this->escape($parts[1]);
        return $this->escape($this->getDefaults()['default_settings']['default_contact_email_domain'] ?? 'example.edu');
    }

    public function loadUploadedInstruments()
    {
        $chunk1 = $this->getSystemSetting("instruments_metadata_1");
        if ($chunk1 === null) {
            $uploaded = $this->getSystemSetting("instruments_metadata");
            if (is_array($uploaded)) return $uploaded;
            if (is_string($uploaded)) {
                $decoded = json_decode($uploaded, true);
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        }

        $chunks = [$this->getSystemSetting("instruments_metadata")];
        $i = 1;
        while (true) {
            $chunk = $this->getSystemSetting("instruments_metadata_" . $i);
            if ($chunk === null) break;
            $chunks[] = $chunk;
            $i++;
        }
        $decoded = json_decode(implode('', $chunks), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function saveUploadedInstruments($uploaded_instruments)
    {
        $json_str = json_encode($uploaded_instruments);
        $chunk_size = 60000;

        if (strlen($json_str) > $chunk_size) {
            $chunks = str_split($json_str, $chunk_size);
            $this->setSystemSetting("instruments_metadata", $chunks[0]);
            for ($i = 1; $i < count($chunks); $i++) $this->setSystemSetting("instruments_metadata_" . $i, $chunks[$i]);

            $i = count($chunks);
            while (true) {
                $setting_name = "instruments_metadata_" . $i;
                if ($this->getSystemSetting($setting_name) === null) break;
                $this->removeSystemSetting($setting_name);
                $i++;
            }
            return;
        }

        $this->setSystemSetting("instruments_metadata", $uploaded_instruments);
        $i = 1;
        while (true) {
            $setting_name = "instruments_metadata_" . $i;
            if ($this->getSystemSetting($setting_name) === null) break;
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
            foreach ($default_cats as $catName) $raw[] = ["name" => $catName];
        } else {
            foreach ($configured as $catName) {
                $name = trim($catName);
                if (!empty($name)) $raw[] = ["name" => $name];
            }
        }

        $nice_colors = $defaults['category_colors'] ?? [];
        if (empty($nice_colors)) throw new Exception("Category color definitions missing in defaults.json.");

        $categories_list = [];
        $color_count = count($nice_colors);
        foreach ($raw as $index => $cat) {
            $color = $nice_colors[$color_count > 0 ? ($index % $color_count) : 0];
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
            $scoped = implode(",\n", array_map(fn($sel) => $sel . " ." . $cat['class'], $scope_selectors));
            return sprintf("%s { background-color: %s !important; color: %s !important; border-color: %s !important; }\n", $scoped, $cat['bg'], $cat['text'], $cat['bg']);
        }, $categories_list));
    }

    public function renderFieldTypeCSS(array $instruments, array $scope_selectors)
    {
        $field_type_colors = $this->getDefaults()['field_type_colors'] ?? [];
        $unique_types = [];

        foreach ($instruments as $inst) {
            if (isset($inst['fields']) && is_array($inst['fields'])) {
                foreach ($inst['fields'] as $field) {
                    $cleanType = str_replace(['_', ' '], '', strtolower(trim($field['type'] ?? '')));
                    if (!empty($cleanType)) $unique_types[$cleanType] = $cleanType;
                }
            }
        }

        $dynamic_styles = "";
        foreach ($unique_types as $cleanType) {
            $colors = $field_type_colors[$cleanType] ?? $field_type_colors['default'];
            $scoped = implode(",\n", array_map(fn($sel) => $sel . " .type-" . $cleanType, $scope_selectors));
            $dynamic_styles .= sprintf("%s { background-color: %s !important; color: %s !important; border-color: %s !important; font-weight: 600 !important; }\n", $scoped, $colors['bg'], $colors['text'], $colors['border']);
        }
        return $dynamic_styles;
    }
}
