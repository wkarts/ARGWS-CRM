<?php defined('BASEPATH') or exit('No direct script access allowed');

use app\services\zip\Unzip;

class Page_builder extends AdminController
{
    public $baseDir;
    public $themeBaseDir;
    public $themeBaseUrl;
    public $themeRealBaseUrl;
    public $mediaDirectoryPath;
    public $mediaDirectoryUrl;
    public $allowedImageExtensions = [];
    public $redirectUrl;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();

        $this->baseDir = module_dir_path(PAGE_BUILDER_MODULE_NAME, '/');

        $this->allowedImageExtensions = page_builder_allowed_image_extensions();

        $landingDir = page_builder_pages_path();

        $mediaFolder = $landingDir . '/media';
        $this->mediaDirectoryPath = FCPATH . $mediaFolder;
        $this->mediaDirectoryUrl = base_url($mediaFolder);

        list($themePath, $themeUrl, $themeRealUrl) = page_builder_get_theme_path_url();
        $this->themeBaseDir = $themePath;
        $this->themeBaseUrl = $themeUrl;
        $this->themeRealBaseUrl = $themeRealUrl;

        if (!has_permission('page_builder', '', 'edit')) {
            redirect(base_url());
        }

        $this->redirectUrl = admin_url(PAGE_BUILDER_MODULE_NAME);
    }

    public function index()
    {
        $data['title'] = _l('page_builder_page_title') . '-' . _l('page_builder_menu_pages');

        $options = page_builder_get_options();
        $data['pagesOptions'] = $options;
        $data['pages'] = page_builder_get_pages();

        try {

            $templateFolderExist = is_dir($this->themeBaseDir);
            $emptyDir = empty($data['pages']);
            $defaultTemplatesFolder = $this->baseDir . 'assets/default_pages';

            if (!$templateFolderExist || $emptyDir) {
                if (!$templateFolderExist)
                    mkdir($this->themeBaseDir, 0755, true);
                page_builder_setup_default_pages($defaultTemplatesFolder, $this->themeBaseDir);
            }

            $defaultTemplateFileName = 'new-page-blank-template.html';
            $defaultBlankTemplate = $defaultTemplatesFolder . '/' . $defaultTemplateFileName;
            $themeDefaultBlankTemplate = $this->themeBaseDir . '/' . $defaultTemplateFileName;
            if (!file_exists($themeDefaultBlankTemplate) && file_exists($defaultBlankTemplate)) {
                copy($defaultBlankTemplate, $themeDefaultBlankTemplate);
            }
        } catch (\Throwable $th) {
            set_alert('danger', $th->getMessage());
            return redirect(admin_url());
        }

        // Getting partial view
        if ($this->input->is_ajax_request()) {
            $options = page_builder_get_options();
            $data['html'] = $this->load->view('includes/_tree_explorer', ['pages' => page_builder_get_pages()], true);
            echo json_encode($data);
            exit;
        }

        $data['controllerUrl'] = admin_url(PAGE_BUILDER_MODULE_NAME);
        $data['pageActionUrl'] = $data['controllerUrl'] . '/page';
        $data['editors'] = page_builder_editors();
        $data['sampleFileLink'] = module_dir_url(PAGE_BUILDER_MODULE_NAME) . 'assets/sample.zip';
        $data['assetPath'] = module_dir_url(PAGE_BUILDER_MODULE_NAME, 'assets');

        $this->load->view('pages', $data);
    }

    public function settings()
    {
        $settings = $this->input->post('settings', true);

        if (!empty($settings)) {
            // Get whitelisted domains and clean
            $whitelist = $settings['whitelist'] ?? '';
            if ($whitelist) {
                $whitelist = explode(',', $whitelist);
                foreach ($whitelist as $key => $value) {
                    $value = trim($value);
                    $whitelist[$key] = $value;
                    if (stripos($value, '/') !== false || !filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
                        unset($whitelist[$key]);
                }
                $whitelist = implode(',', $whitelist);
            }
            $settings['whitelist'] = $whitelist;
            page_builder_save_settings($settings);

            echo json_encode(['message' => _l('page_builder_saved'), 'allowed_hosts' => page_builder_whitelisted_hosts()]);
            exit;
        }
    }

    /**
     * Launch page editor into view
     *
     * @return void
     */
    public function builder()
    {
        $pageInfo = [];
        $builder = '';
        if ($this->input->method(true) !== 'POST') {
            if (empty($pageInfo = $this->session->userdata('page_builder_file_to_edit_info'))) {
                return redirect($this->redirectUrl);
            }
            $builder = $pageInfo['builder'] ?? '';
        } else {
            $file_to_edit = $this->input->post('file');
            $builder = $this->input->post('builder', true);
            $pageInfo = [];
            $pages = page_builder_get_pages();
            foreach ($pages as $key => $file) {
                if ($file['file'] == $file_to_edit) {
                    $pageInfo = $file;
                    break;
                }
            }

            if (!empty($pageInfo)) {
                $pageInfo['builder'] = $builder;
                $this->session->set_userdata('page_builder_file_to_edit_info', $pageInfo);
                return redirect(admin_url(PAGE_BUILDER_MODULE_NAME . '/builder'),);
            }
        }

        $data['title'] = _l('page_builder_page_title');
        $options = page_builder_get_options();

        if (empty($pageInfo['fullpath'] ?? '')) {
            set_alert('danger', _l('page_builder_page_info_not_found'));
            return redirect($this->redirectUrl);
        }

        if (empty($builder)) {
            $builder = array_keys(page_builder_editors())[0];
        }

        $data['landingpagesBaseUrl'] = module_dir_url(PAGE_BUILDER_MODULE_NAME, 'views/');
        $data['mediaDirectoryUrl'] = $this->mediaDirectoryUrl;
        $data['pagesOptions'] = $options;

        $_metadata = page_builder_get_metadata($pageInfo['fullpath']);
        $metadata = [];
        foreach ($_metadata as $key => $value) {
            if (isset(PAGE_BUILDER_TAGS[$key]))
                $key = PAGE_BUILDER_TAGS[$key];
            $metadata[$key] = str_starts_with($key, '_') ? $value : xss_clean(page_builder_remove_css_comments($value));
        }

        /**
         * Snapshot was used mainly for JS blocks/component mainly and it is prevented from being saved when scripting is disabled
         * Force use of saved html by emptying builder snapshot when scripting is not enabled.
         * Implecation: JS component state tracking will be lost. _builder_snapshot is used mainly in GrapesJs
         */
        if (page_builder_scripting_disabled() && isset($metadata['_builder_snapshot'])) {

            $metadata['_builder_snapshot'] = '';
        }

        $data['pageMetadata'] = $metadata;

        $data['builder'] = $builder;

        $data['isRTL'] = (is_rtl() ? 'true' : 'false');
        $data['controllerUrl'] = admin_url(PAGE_BUILDER_MODULE_NAME);
        $data['pageActionUrl'] = $data['controllerUrl'] . '/page';
        $data['themeBaseUrl'] = $this->themeBaseUrl;
        $data['assetPath'] = module_dir_url(PAGE_BUILDER_MODULE_NAME, 'assets');
        $data['builderAssetPath'] = module_dir_url(PAGE_BUILDER_MODULE_NAME, 'assets/' . $builder);

        // Unset sensitive info for client
        unset($pageInfo['fullpath']);

        // Set page information
        $data['pages'] = [$pageInfo];

        $this->load->view('builders/' . $builder, $data);
    }

    /**
     * Method to handle page actions.
     * It handly page copy, rename, save and delete.
     *
     * @param string $action The action to perform
     * @return void
     */
    public function page($action = '', $actionFileType = 'file')
    {
        if (defined('PAGE_BUILDER_DEMO_MODE_ENABLED'))
            return $this->showError("For security reasons, saving is disabled in the demo.");

        $forceSanitation = page_builder_scripting_disabled();

        // Get input data from the POST request and sanitize
        $file   = $this->input->post('file', true) ?? '';
        $newfile = $this->input->post('newfile', true) ?? '';
        $formOptions = $this->input->post('options', true);
        $duplicate = $this->input->post('duplicate', true) === 'true';
        $startTemplateFile = $this->input->post('startTemplateFile', true) ?? '';
        $metadata = $this->input->post('metadata', true);

        $folder   = trim(trim($this->input->post('folder', true) ?? ''), '/');
        if (empty($action) && !empty($folder)) {
            $file = '/' . $folder . '/' . end(explode('/', $file));
        }

        // Don't do XSS here till we have the final html content
        $html   = $this->input->post('html', false) ?? '';

        // Get the starter template if provided
        if (!empty($startTemplateFile)) {
            $startTemplateFile = $this->sanitizeFileName($startTemplateFile, true);
            $html = file_get_contents($startTemplateFile);
        }

        // Purify HTML
        $html = page_builder_html_purify($html);

        // Component CSS
        $componentsCss = $this->input->post('css', true); // Always sanitize
        if (!is_null($componentsCss)) {
            $metadata['components_css'] = $componentsCss;
        }

        // Allow saving vulnerable content when script is enabled only
        if (!$forceSanitation) {
            // Component JS
            $componentsJs = $this->input->post('js', false);
            if (!is_null($componentsJs)) {
                $metadata['_components_js'] = $componentsJs;
            }

            // Builder snapshot
            $builderSnapshot = $this->input->post('_builder_snapshot', false);
            if (!empty($builderSnapshot)) {
                $metadata['_builder_snapshot'] = $builderSnapshot;
            }

            // Dangerous extra code
            $dangerousExtraCode = $this->input->post('_dangerous_extra_custom_code', false);
            if (!is_null($dangerousExtraCode)) {
                $metadata['_dangerous_extra_custom_code'] = $dangerousExtraCode;
            }
        }

        // Validate html content size
        if (!empty($html)) {

            $fileSizeLimit = 1024 * 1024 * 2; //2 Megabytes max html file size
            if (strlen($html) > $fileSizeLimit)
                return $this->showError(_l('page_builder_content_exceed_file_size', '2mb'));
        }

        // File is required for all actions
        if (empty($file))
            return $this->showError(_l('page_builder_builder_filename_empty'));

        // Restrict writing to the theme base dir only or the media folder
        $file = $this->sanitizeFileName($file, true);
        $prettyFileName = $this->prettyFileName($file);
        $validFile = str_starts_with($file, $this->themeBaseDir) || str_starts_with($file, $this->mediaDirectoryPath);
        if (!$validFile) {

            return $this->showError(_l('page_builder_builder_wrong_filepath', [$prettyFileName]));
        }

        if ($action) {

            switch ($action) {

                case 'rename':
                    // Editing file name or copying
                case 'update':

                    // Require newfile 
                    if (empty($newfile))
                        return $this->showError(_l('page_builder_builder_newfilename_empty'));

                    // Sanitize file name and validate
                    $newfile = $this->sanitizeFileName($newfile, true);
                    $prettyNewfileName = $this->prettyFileName($newfile);
                    $validNewFile = str_starts_with($newfile, $this->themeBaseDir) || str_starts_with($newfile, $this->mediaDirectoryPath);
                    if (empty($newfile) || !$validNewFile) {

                        return $this->showError(_l('page_builder_builder_wrong_filepath', [$prettyNewfileName]));
                    }

                    // Rename the file
                    if (!$duplicate && $file !== $newfile) {

                        if (!file_exists($file) || !rename($file, $newfile)) {

                            return $this->showError(_l('page_builder_builder_error_updating_file', [$prettyFileName, $prettyNewfileName]));
                        }
                    }

                    // Perform copy action
                    if ($duplicate) {

                        $dir = dirname($newfile);
                        if (!is_dir($dir)) mkdir($dir, 0755, true);

                        if (!file_exists($file) || !copy($file, $newfile))
                            return $this->showError(_l('page_builder_builder_error_copying_file', [$prettyFileName, $prettyNewfileName]));
                    }


                    // Update options i.e landingpage option
                    $settings = page_builder_get_settings();
                    if (!empty($formOptions)) {

                        $markAsLanding = $formOptions['landingpage'] === 'yes';

                        // Get current landing page
                        $landingpage = $settings['landingpage'] ?? '';

                        // Update
                        if ($markAsLanding) {
                            $landingpage = empty($newfile) ? $file : $newfile;
                        } else if ($landingpage == str_ireplace($this->themeBaseDir, '', $file)) {
                            $landingpage = '';
                        }
                        $settings['landingpage'] = str_ireplace($this->themeBaseDir, '', $landingpage);
                        page_builder_save_settings($settings);
                    }

                    if ($actionFileType == 'media') {
                        echo _l('updated_successfully');
                        exit;
                    }

                    // Update page metadata
                    page_builder_metadata($file, $metadata, $newfile);


                    $options = page_builder_get_options();

                    // Return JSON
                    $actionMode = $duplicate ? _l('page_builder_builder_copied') : _l('page_builder_builder_updated');
                    $message = _l('page_builder_builder_file_action', [$prettyFileName, $actionMode, $this->prettyFileName($newfile)]);
                    if (!$duplicate) $message = _l('updated_successfully', _l('page_builder_page'));

                    echo json_encode(['message' =>  $message, 'pagesOptions' => empty($options) ? [] : $options]);
                    exit;

                    break;

                case 'delete':

                    if (!file_exists($file) || !unlink($file)) {

                        return $this->showError(_l('page_builder_builder_error_deleting_file', [$prettyFileName]));
                    }

                    //Remove metatdata
                    page_builder_metadata($file);


                    $pathInfo = pathinfo($file);
                    if (!empty($pathInfo['extension']) && $pathInfo['extension'] === "html") {
                        // Remove the directory also if no more files
                        $themeFolder = explode('/', str_ireplace(rtrim($this->themeBaseDir, '/') . '/', '', dirname($file)))[0] ?? '';
                        $themeFolder = trim(str_ireplace('/', '', $themeFolder));
                        if (!empty($themeFolder)) {

                            $themePath = $this->themeBaseDir . '/' . $themeFolder;
                            $htmlFiles = page_builder_get_dir_html_files($themePath);

                            if (
                                empty($htmlFiles) &&
                                $this->themeBaseDir != $themePath &&
                                $this->themeBaseDir . '/' != $themePath &&
                                $this->mediaDirectoryPath != $themePath
                            ) {

                                page_builder_remove_dir($themePath);
                            }
                        }
                    }

                    echo _l('page_builder_builder_file_deleted', [$prettyFileName]);
                    exit;
                    break;
                default:
                    return $this->showError(_l('page_builder_builder_invalid_action', [$action]));
            }
        } else {
            // Save page content to html file
            if (!$html)
                return $this->showError(_l('page_builder_builder_html_content_empty'));

            $dir = dirname($file);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {

                return $this->showError(_l('page_builder_builder_folder_not_exist', [$this->prettyFileName($dir)]));
            }

            // Allow only .html extension here
            $pathInfo = pathinfo($file);
            if (empty($pathInfo['extension']) || $pathInfo['extension'] !== "html" || !str_starts_with($pathInfo['dirname'], $this->themeBaseDir))
                throw new \Exception("Error Processing Request", 1);

            $file = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . ".html";
            $prettyFileName = $this->prettyFileName($file);

            try {
                // Save content to file
                if (!file_put_contents($file, $html))
                    return $this->showError(_l('page_builder_builder_error_saving_file', [$prettyFileName]));

                if ($startTemplateFile) {

                    if (empty($metadata)) {
                        $metadata = page_builder_get_metadata($startTemplateFile);
                    }

                    // Copy the start template asset files
                    $assetFolder = $this->getThemeBasePathFromFile($startTemplateFile, 'assets');
                    $destAssetFolder = $this->getThemeBasePathFromFile($file, 'assets');
                    if (is_dir($assetFolder) && !is_dir($destAssetFolder)) {
                        xcopy($assetFolder, $destAssetFolder);
                    }
                }

                // Update metadata
                if (!empty($metadata))
                    page_builder_metadata($file, $metadata);
            } catch (\Throwable $th) {

                return $this->showError($th->getMessage());
            }

            echo _l('page_builder_builder_file_saved', [$prettyFileName]);
            exit;
        }
    }

    /**
     * Scan media folder for all media files to be display in builder media modal.
     *
     * @return void
     */
    public function media_scan()
    {

        $scandir = $this->mediaDirectoryPath;

        // If not exit, attempt creating and copy the default media files to the location.
        if (!is_dir($this->mediaDirectoryPath) && mkdir($this->mediaDirectoryPath, 0755, true)) {
            xcopy($this->baseDir . 'assets/vvvebjs/media', $this->mediaDirectoryPath);
        }

        // Run the recursive function
        // This function scans the files folder recursively, and builds a large array

        $scan = function ($dir) use ($scandir, &$scan) {
            $files = [];

            // Is there actually such a folder/file?

            if (file_exists($dir)) {
                foreach (scandir($dir) as $f) {
                    if (!$f || $f[0] == '.') {
                        continue; // Ignore hidden files
                    }

                    if (is_dir($dir . '/' . $f)) {
                        // The path is a folder

                        $files[] = [
                            'name'  => $f,
                            'type'  => 'folder',
                            'path'  => str_replace($scandir, '', $dir) . '/' . $f,
                            'items' => $scan($dir . '/' . $f), // Recursively get the contents of the folder
                        ];
                    } else {
                        // It is a file

                        $files[] = [
                            'name' => $f,
                            'type' => 'file',
                            'path' => str_replace($scandir, '', $dir) . '/' . $f,
                            'size' => filesize($dir . '/' . $f), // Gets the size of this file
                        ];
                    }
                }
            }

            return $files;
        };

        $response = $scan($scandir);

        // Output the directory listing as JSON

        header('Content-type: application/json');
        echo json_encode([
            'name'  => '',
            'type'  => 'folder',
            'path'  => '',
            'items' => $response,
        ]);
        exit;
    }

    /**
     * Handle file upload from the media modal
     *
     * @return void
     */
    public function media_upload()
    {
        $fileName  = $_FILES['file']['name'];
        $extension = strtolower(substr($fileName, strrpos($fileName, '.') + 1));

        // check if extension is on allow list
        if (!in_array($extension, $this->allowedImageExtensions)) {
            return $this->showError(_l('page_builder_builder_file_not_allowed', [$extension]));
        }

        if (!is_dir($this->mediaDirectoryPath) && !mkdir($this->mediaDirectoryPath, 0755, true))
            return $this->showError(_l('page_builder_builder_error_creating_folder', [$this->mediaDirectoryPath]));

        try {
            $destination = $this->sanitizeFileName($this->mediaDirectoryPath . '/' . $fileName);
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination))
                return $this->showError(_l('page_builder_builder_file_not_uploaded', [$destination]));
        } catch (\Throwable $th) {
            return $this->showError($th->getMessage());
        }

        if ($this->input->post('onlyFilename', true)) {
            echo $fileName;
        } else {
            echo $destination;
        }
    }

    /**
     * A bridge method to remove media.
     *
     * @return void
     */
    public function media_delete()
    {
        $file = $this->input->post('file', true);
        $file = $this->mediaDirectoryPath . '/' . str_ireplace($this->mediaDirectoryUrl, '', $file);
        $_POST['file'] = $file;
        return $this->page('delete');
    }


    /**
     * Handle upload of new theme to the theme folder.
     * Should scan and only allow safe static files.
     */
    public function upload_template()
    {
        try {

            $this->load->library('upload');
            $this->load->helper('file');

            // Set upload configuration
            $config['upload_path']      = get_temp_dir(); // Temporary upload directory
            $config['allowed_types']    = 'zip';
            $config['max_size']         = 10240; // 10 MB maximum file size

            $this->upload->initialize($config);
            if (!$this->upload->do_upload('template_file')) {
                throw new Exception($this->upload->display_errors(), 1);
            }

            // Upload successful, now extract the zip file
            $upload_data = $this->upload->data();
            $zip_file = $upload_data['full_path'];

            // Get theme name
            $template_name = $this->input->post('template_name', true);
            if (empty($template_name)) {
                $template_name = slug_it($upload_data['file_name']);
            }
            $template_name = strtolower($template_name);

            $_new_template_path = $this->themeBaseDir . '/' . $template_name;
            if (is_dir($_new_template_path))
                throw new \Exception(_l('page_builder_template_name_taken'), 1);

            // Create temporary directory for extraction
            $temp_dir = page_builder_create_temp_dir();

            // Extract the zip file
            $unzip = new Unzip();
            $unzip->extract($zip_file, $temp_dir);

            page_builder_import_pages_from_folder($temp_dir, $template_name);

            // Clean up temporary files and directory
            page_builder_remove_dir($temp_dir);
            unlink($zip_file);

            $message =  _l('added_successfully', _l('page_builder_builder_template'));
            $status = 'success';
        } catch (\Throwable $th) {
            $status = 'danger';
            $message = $th->getMessage();

            if (isset($zip_file) && file_exists($zip_file)) {
                unlink($zip_file);
            }

            if (isset($new_template_path) && is_dir($new_template_path)) {
                page_builder_remove_dir($new_template_path);
            }
        }

        if ($this->input->is_ajax_request()) {
            $options = page_builder_get_options();
            ob_clean();
            echo json_encode(['status' => $status, 'message' => $message, 'pagesOptions' => (array)$options]);
            exit;
        }

        set_alert($status, $message);
        return redirect($this->redirectUrl);
    }

    /**
     * Emebed component proxy.
     * Mainly used by vvveb builder
     *
     * @return void
     */
    public function oembed_proxy()
    {
        $url = $this->input->get('url', true);
        $urlHost = parse_url($url, PHP_URL_HOST);
        $allowedHosts = page_builder_whitelisted_hosts();
        if (!in_array($urlHost, $allowedHosts)) {
            return $this->showError(_l('page_builder_invalid_url'));
        }
        header('Content-Type: application/json');
        echo file_get_contents($url);
    }

    /**
     * Validate and sanitize file name.
     * It cleans and validate safe extension
     *
     * @param string $file
     * @param boolean $appendThemeDir
     * @return mixed yhcurnaL
     */
    private function sanitizeFileName($file, $appendThemeDir = false)
    {
        // Sanitize, remove double dot .. and remove get parameters if any
        $file = preg_replace('@\?.*$@', '', preg_replace('@\.{2,}@', '', preg_replace('@[^\/\\a-zA-Z0-9\-\._]@', '', $file)));

        $pathInfo  = pathinfo($file);
        $extension = $pathInfo['extension'] ?? '';
        $dir = $pathInfo['dirname'];
        $dir = (empty($dir) || $dir == '.' ? '/' : $dir . '/');
        $file = $dir . sanitize_filename($pathInfo['basename']);

        if ($appendThemeDir) {

            $file = str_ireplace([$this->themeBaseDir, $this->mediaDirectoryPath], '', $file);

            if ($extension === 'html') {
                $file = str_ireplace($this->themeBaseDir, '', $file);
                $file = $this->themeBaseDir . $file;
            } else {
                // Media files. Must start with media dir ul
                if (str_starts_with($file, $this->mediaDirectoryUrl))
                    $file = str_ireplace($this->mediaDirectoryUrl, $this->mediaDirectoryPath, $file);
            }
        }

        $file = str_ireplace('//', '/', '/' . $file);

        // Check if extension is on allow list
        if ($extension !== 'html' && !in_array($extension, $this->allowedImageExtensions)) {
            return $this->showError(_l('page_builder_builder_file_not_allowed', [$extension]));
        }

        return $file;
    }

    /**
     * Remove the base dir form a string
     *
     * @param string $path
     * @return string
     */
    private function prettyFileName($path)
    {
        return trim(str_ireplace([$this->themeBaseDir, $this->mediaDirectoryPath], '', $path), '/');
    }

    /**
     * Display error with 500 header
     *
     * @param string $error
     * @return void
     */
    private function showError($error)
    {
        set_status_header(500, $error);
        echo $error;
        exit;
    }

    /**
     * Get the full absolute path of a theme from a file
     *
     * @param string $file
     * @param string $path
     * @return string
     */
    function getThemeBasePathFromFile($file, $path = '')
    {
        $theme = explode('/', trim(dirname(str_ireplace($this->themeBaseDir, '', $file)), '/'))[0];
        return $this->themeBaseDir . '/' . $theme . '/' . $path;
    }
}