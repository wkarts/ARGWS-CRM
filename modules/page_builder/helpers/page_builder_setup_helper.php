<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Generates a regular expression pattern to match the signature for requiring a file.
 *
 * The signature pattern is in the format:
 *     #//page-builder:start:<filename>([\s\S]*)//page-builder:end:<filename>#
 * where <filename> is the basename of the file.
 *
 * @param string $file The path to the file.
 *
 * @return string The regular expression pattern for the file signature.
 */
function page_builder_require_signature($file)
{
    $basename = str_ireplace(['"', "'"], '', basename($file));
    return '#//page-builder:start:' . $basename . '([\s\S]*)//page-builder:end:' . $basename . '#';
}

/**
 * Generates the template for requiring a file in a hosted SaaS installation.
 *
 * This function generates the template for requiring a file in a hosted SaaS installation. The template includes comments that mark
 * the start and end of the required file. The template is in the following format:
 *     //page-builder:start:#filename
 *     //dont remove/change above line
 *     require_once('#path');
 *     //dont remove/change below line
 *     //page-builder:end:#filename
 * where #filename is replaced with the basename of the file, and #path is replaced with the actual path to the file.
 *
 * @param string $path The path to the file.
 *
 * @return string The template for requiring the file.
 */
function page_builder_require_in_file_template($path)
{
    $template = "//page-builder:start:#filename\n//dont remove/change above line\nrequire_once(#path);\n//dont remove/change below line\n//page-builder:end:#filename";

    $template = str_ireplace('#filename', str_ireplace(['"', "'"], '', basename($path)), $template);
    $template = str_ireplace('#path', $path, $template);
    return $template;
}

/**
 * Writes content to a file.
 *
 * It sets the appropriate file permissions, opens the file,
 * writes the content, and closes the file.
 *
 * @param string $path    The path to the file.
 * @param string $content The content to write to the file.
 *
 * @return bool True if the write operation was successful, false otherwise.
 */
function page_builder_file_put_contents($path, $content)
{
    @chmod($path, FILE_WRITE_MODE);
    if (!$fp = fopen($path, FOPEN_WRITE_CREATE_DESTRUCTIVE)) {
        return false;
    }
    flock($fp, LOCK_EX);
    fwrite($fp, $content, strlen($content));
    flock($fp, LOCK_UN);
    fclose($fp);
    @chmod($path, FILE_READ_MODE);
    return true;
}

/**
 * Requires a file into another file.
 *
 * The function uses a template to generate the require statement and inserts it at the specified
 * position in the destination file. If no position is specified, the require statement is appended to the end of the
 * file.
 *
 * @param string  $dest        The path to the destination file.
 * @param string  $requirePath The path to the file to require.
 * @param bool    $force       Whether to force the insertion even if it already exists.
 * @param int|bool $position    The position to insert the require statement. False to append to the end of the file.
 *
 * @return void
 */
function page_builder_require_in_file($dest, $requirePath, $force = false, $position = false)
{
    if (!file_exists($dest)) {
        page_builder_file_put_contents($dest, "<?php defined('BASEPATH') or exit('No direct script access allowed');\n");
    }

    if (file_exists($dest)) {
        $content = file_get_contents($dest);  // Fetch the content inside the file

        $template = page_builder_require_in_file_template($requirePath);

        $exist = preg_match(page_builder_require_signature($requirePath), $content);
        if ($exist && !$force) { // Check if this process has run once or not
            return;
        }
        $content = page_builder_unrequire_in_file($dest, $requirePath);

        if ($position !== false) {
            $content = substr_replace($content, $template . "\n", $position, 0);
        } else {
            $content = $content . $template;
        }

        page_builder_file_put_contents($dest, $content);
    }
}

/**
 * Removes the require statement of a file.
 *
 * This function removes the require statement from a file in a hosted SaaS installation.
 * It fetches the content inside the destination file, replaces the require statement with an
 * empty string using a regular expression, and then updates the destination file with the modified content.
 *
 * @param string $dest        The path to the destination file.
 * @param string $requirePath The path to the file to be removed from the require statement.
 *
 * @return string The modified content of the destination file.
 */
function page_builder_unrequire_in_file($dest, $requirePath)
{
    if (file_exists($dest)) {
        $content = file_get_contents($dest);  // Fetch the content inside the file
        $content = preg_replace(page_builder_require_signature($requirePath), '', $content);
        page_builder_file_put_contents($dest, $content);
        return $content;
    }
}

/**
 * Installs Page builder
 *
 * @return void
 */
function page_builder_install()
{
    // Require the SAAS routes and hooks
    page_builder_require_in_file(APPPATH . 'config/my_routes.php', "FCPATH.'modules/" . PAGE_BUILDER_MODULE_NAME . "/config/my_routes.php'");

    require module_dir_path(PAGE_BUILDER_MODULE_NAME, 'install.php');
}


/**
 * Uninstalls Page builder
 * @param bool $clean (Optional) Determines whether to perform a clean uninstall by removing all data. Defaults to false.
 * @return void
 */
function page_builder_uninstall()
{
    // Remove the SAAS routes and hooks
    page_builder_unrequire_in_file(APPPATH . 'config/my_routes.php', "FCPATH.'modules/" . PAGE_BUILDER_MODULE_NAME . "/config/my_routes.php'");
}


/**
 * Import default pages into the the public theme folder. This will attempt to sync all the default pages.
 * Should only be used when the default pages folder is cleared.
 *
 * @param string $pagesSourceDir
 * @param string $destDir
 * @param string $templateName (Optional) Specify to limit setup to only this template
 * @return void
 */
function page_builder_setup_default_pages($pagesSourceDir, $destDir, $templateName = '')
{

    //@todo Copy each start files
    xcopy($pagesSourceDir, $destDir);
    page_builder_remove_dir($destDir . '/assets');


    $iterator = new DirectoryIterator($destDir);
    foreach ($iterator as $fileinfo) {

        // Skip . and ..
        if ($fileinfo->isDot()) {
            continue;
        }

        // Import folders content
        if ($fileinfo->isDir()) {
            $folderName = $fileinfo->getFilename();
            if ($folderName == 'assets' || $folderName == 'asset') continue;

            if (!empty($templateName) && $folderName !== $templateName) continue;

            $folder = $fileinfo->getRealPath();
            // Create temporary directory for extraction
            $tempDir = page_builder_create_temp_dir();

            xcopy($folder, $tempDir);
            page_builder_remove_dir($folder);
            page_builder_import_pages_from_folder($tempDir, $folderName);

            // Clean up temporary files and directory
            page_builder_remove_dir($tempDir);
        }
    }
}

/**
 * Import selected template from default pages folder. Only use for templates having folder 
 *
 * @param array $templates
 * @return void
 */
function page_builder_import_default_templates($templates = [])
{
    list($themePath) = page_builder_get_theme_path_url();

    foreach ($templates as $templateName) {

        if (is_dir($themePath . '/' . $templateName)) continue;

        $tempDir = page_builder_create_temp_dir($templateName);

        $sourceDir = module_dir_path(PAGE_BUILDER_MODULE_NAME, '/assets/default_pages/' . $templateName);
        if (!is_dir($sourceDir)) continue;

        xcopy($sourceDir, $tempDir);

        $tempDir = dirname(rtrim($tempDir, '/')); // go the up one level to reveal the template name itself in temp dir

        page_builder_setup_default_pages($tempDir, $themePath, $templateName);

        page_builder_remove_dir($tempDir);
    }
}

/**
 * Function to assist in migrating saved pages from a legacy SaaS extension to this builder.
 *  @since 1.0.1
 * @return void
 * @todo Drop this implementation when all customers must have migrated
 */
function page_builder_migrate_from_saas()
{
    // Location where the SaaS store own landing pages
    $saasThemePath =  FCPATH . get_instance()->app->get_media_folder() . '/public/landingpage/themes/';

    if (!is_dir($saasThemePath)) return;

    $iterator = new DirectoryIterator($saasThemePath);
    foreach ($iterator as $fileinfo) {
        $folder = '';
        $file_name = '';

        // Skip . and ..
        if ($fileinfo->isDot()) {
            continue;
        }

        // If it's a directory
        if ($fileinfo->isDir()) {
            $folder = $fileinfo->getFilename();
        }

        // If it's a file and has .html extension
        elseif ($fileinfo->isFile() && $fileinfo->getExtension() === 'html') {
            $folder = '';
        } else {
            continue;
        }

        // Create temporary directory parsing
        $temp_dir = page_builder_create_temp_dir();

        if (empty($folder)) {
            $file_name = $fileinfo->getFilename();
            if ($file_name == 'new-page-blank-template.html') continue;
            copy($saasThemePath . $file_name, $temp_dir . $file_name);
        } else {
            xcopy($saasThemePath . $folder, $temp_dir);
        }

        try {
            page_builder_import_pages_from_folder($temp_dir, $folder, !empty($file_name));

            page_builder_remove_dir($temp_dir);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }
    }

    $option = get_option('perfex_saas_landing_page_theme');
    if (!empty($option)) {
        page_builder_save_settings(['landingpage' => $option]);
    }
}
