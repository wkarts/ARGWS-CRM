<?php
defined('BASEPATH') or exit('No direct script access allowed');

require(__DIR__ . '/../vendor/autoload.php');

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

require_once(__DIR__ . '/page_builder_custom_attribute_sanitizer.php');

/** [TAG] => POST key map for METADATA */
const PAGE_BUILDER_TAGS = [
    '[PAGE_BUILDER_TITLE]' => 'title',
    '[PAGE_BUILDER_SEO_DESCRIPTION]' => 'description',
    '[PAGE_BUILDER_SEO_AUTHOR]' => 'author',
    '[PAGE_BUILDER_SEO_KEYWORDS]' => 'keywords',
    '[PAGE_BUILDER_FAVICON]' => 'favicon',
    '[PAGE_BUILDER_SEO_IMAGE]' => 'og:image',
    '[PAGE_BUILDER_SEO_ROBOT]' => 'robots',
    '[PAGE_BUILDER_CANONICAL]' => 'canonical',
    '[PAGE_BUILDER_CUSTOM_CSS]' => 'css',
    '[PAGE_BUILDER_COMPONENTS_CSS]' => 'components_css',
    '[PAGE_BUILDER_COMPONENTS_JS]' => '_components_js',
    '[PAGE_BUILDER_DANGEROUS_EXTRA_CUSTOM_CODE]' => '_dangerous_extra_custom_code',
    '[PAGE_BUILDER_LAYOUT_TEMPLATE]' => 'layout_template',
];

function page_builder_scripting_disabled()
{
    return defined('PAGE_BUILDER_DISABLE_SCRIPT');
}

/**
 * List of whitelisted host for iframes, img and medias
 *
 * @return array
 */
function page_builder_whitelisted_hosts()
{
    $allowedHosts = [
        'youtube.com',
        'player.vimeo.com',
        'twitter.com',
        'x.com',
        'openstreetmap.org',
        'google.com',
        'googleapis.com',
        'paypalobjects.com',
        'paypal.com',
        'unsplash.com',
        'placeholder.com',
        'wrappixel.com'
    ];

    $allowedHosts[] = parse_url(base_url(), PHP_URL_HOST);

    $customWhitelist = explode(',', (page_builder_get_settings()['whitelist'] ?? ''));
    $allowedHosts = array_merge($allowedHosts, $customWhitelist);

    return $allowedHosts;
}


/**
 * Act on a page metadata.
 * The function will copy, renaname, update or delete the metadata for the page.
 * @param string $file . Should exist except for delete and rename action.
 * @param array $metadata
 * @param string $newfile . should exist for renaming or duplicating action
 * @return array Builder options
 */
function page_builder_metadata(string $file, array $metadata = [], $newfile = '')
{

    $fileHash = hash("md5", $file);
    $newfileHash = empty($newfile) ? '' : hash("md5", $newfile);

    $options = [
        $fileHash => page_builder_get_metadata($file),
        $newfileHash => empty($newfileHash) ? [] : page_builder_get_metadata($newfile)
    ];

    $fileExist  = file_exists($file);
    $newfileExist = !empty($newfile) && file_exists($newfile);

    // Clean the metadata for use where neccessary
    $cleanedMetadata = [];
    if (!empty($metadata)) {
        // Extract the needed data and clean
        $tags = array_flip(PAGE_BUILDER_TAGS);
        foreach ($metadata as $key => $value) {
            $value = str_starts_with($key, '_') ? $value : xss_clean(page_builder_remove_css_comments($value));
            if (isset($tags[$key])) {
                $key = $tags[$key];
            }
            $cleanedMetadata[$key] = $value;
        }
    }


    // Delete
    if (!$fileExist && empty($newfile) && isset($options[$fileHash])) {
        unset($options[$fileHash]);
    }

    // Copying or updating details
    if ($fileExist && $newfileExist && isset($options[$fileHash])) {
        $options[$newfileHash] = array_merge($options[$fileHash] ?? [], $cleanedMetadata);
    }

    // Renaming
    if (!$fileExist && $newfileExist) {

        if (isset($options[$fileHash])) {
            $options[$newfileHash] = $options[$fileHash];
            unset($options[$fileHash]);
        }
    }

    // Updating
    if ($fileExist && (empty($newfile) || $newfile === $file) && !empty($metadata)) {
        // Merge with old
        $options[$fileHash] = array_merge($options[$fileHash] ?? [], $cleanedMetadata);
    }

    // Save
    page_builder_save_metadata($file, $options[$fileHash] ?? []);
    if (!empty($newfileHash))
        page_builder_save_metadata($newfile, $options[$newfileHash] ?? []);

    return page_builder_get_metadata();
}


/**
 * Will save or update or delete meta data for the file
 *
 * @param string $file
 * @param array $metadata
 * @return mixed
 */
function page_builder_save_metadata(string $file, array $metadata)
{
    $hash = hash("md5", $file);

    $db = get_instance()->db;
    $table = db_prefix() . PAGE_BUILDER_MODULE_NAME . '_pages';

    // Delete when empty content
    if (empty($metadata)) {
        return $db->where('hash', $hash)->delete($table);
    }

    $_meta = $db->where('hash', $hash)->get($table)->row_array();
    if (isset($_meta['hash'])) {
        // Update
        return $db->update($table, ['file' => $file, 'metadata' => json_encode($metadata)], ['hash' => $hash]);
    }

    // Save
    return $db->insert($table, ['file' => $file, 'hash' => $hash, 'metadata' => json_encode($metadata)]);
}

/**
 * Get metadata options for a page file(s).
 *
 * @param string $file Optional. Set empty to get for all pages
 * @return array
 */
function page_builder_get_metadata(string $file = '')
{
    $db = get_instance()->db;
    $table = db_prefix() . PAGE_BUILDER_MODULE_NAME . '_pages';
    $hash = empty($file) ? '' : hash("md5", $file);

    if (!empty($hash)) {
        $db->where('hash', $hash);
    }
    $result = $db->get($table)->result_array();

    $_metas = [];
    foreach ($result as $row) {
        $_metas[$row['hash']] = json_decode($row['metadata'], true);
    }
    return !empty($hash) ? $_metas[$hash] ?? [] : $_metas;
}

/**
 * Get the common settings for the page builder.
 *
 * @return array
 */
function page_builder_get_settings()
{
    return json_decode(get_option('page_builder_options') ?? '', true) ?? [];
}

/**
 * Save page builder common ext settings
 *
 * @param array $options
 * @return bool
 */
function page_builder_save_settings($options)
{
    $oldoptions = (array)page_builder_get_settings();
    $options = array_merge($oldoptions, $options);
    return update_option('page_builder_options', json_encode($options), false);
}

/**
 * Get all the options (settings and pages options) for the page builder.
 *
 * @return array
 */
function page_builder_get_options()
{
    $settings = page_builder_get_settings();
    $pagesOptions = page_builder_get_metadata();
    return array_merge($pagesOptions, $settings);
}

/**
 * Remove css comment from string
 *
 * @param string $cssString
 * @return void
 */
function page_builder_remove_css_comments(string $cssString)
{
    // Regular expression to match CSS comments (/* ... */)
    $pattern = '/\/\*.*?\*\//s';

    // Remove CSS comments using preg_replace
    $cleanedString = preg_replace($pattern, '', $cssString);

    return $cleanedString;
}

/**
 * Remove directory recursively including hidder directories and files.
 * This also handles hidden directories when removing page-builder work files.
 *
 * @param      string  $target  The directory to remove
 * @return     bool
 */
function page_builder_remove_dir($target)
{
    try {
        if (is_dir($target)) {
            $dir = new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST) as $filename => $file) {
                if (is_file($filename)) {
                    unlink($filename);
                } else {
                    page_builder_remove_dir($filename);
                }
            }
            return rmdir($target); // Now remove target folder
        }
    } catch (\Exception $e) {
    }
    return false;
}


/**
 * Get the path where pages are stored.
 * This is not absolute but relative to the media folder.
 *
 * @param string $extra_path
 * @return string
 */
function page_builder_pages_path($extra_path = '')
{
    $extra_path = empty($extra_path) || str_starts_with($extra_path, '/') ? $extra_path : '/' . $extra_path;
    return  get_instance()->app->get_media_folder() . '/public/' . PAGE_BUILDER_MODULE_NAME . $extra_path;
}

/**
 * Get the path and url of the theme.
 * Path first the http url.
 *
 * @return array Path first, theme fancy url and real http url without trailing slash
 */
function page_builder_get_theme_path_url($path = null)
{
    $path = $path ? $path : page_builder_pages_path('/pages');
    $themePath = FCPATH . $path;
    $themeRealUrl = base_url($path);
    $themeUrl = trim(base_url(), '/');
    return [$themePath,  $themeUrl, $themeRealUrl];
}

/**
 * Get all html files inside a dir
 *
 * @param string $dir The folder to fetch html files from
 * @return array
 */
function page_builder_get_dir_html_files($dir)
{
    $htmlFiles = [];

    // Ensure the directory exists
    if (!is_dir($dir)) return $htmlFiles;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $path) {
        if ($path->isFile() && preg_match('/^html?$/i', pathinfo($path->getFilename(), PATHINFO_EXTENSION)))
            $htmlFiles[] = $path->getPathName();
    }

    return $htmlFiles;
}

/**
 * Get all html pages in the pages folder.
 * The use can select which page to use as the landing page.
 *
 * @return array
 */
function page_builder_get_pages()
{
    $pages = [];
    list($themePath, $themeUrl) = page_builder_get_theme_path_url();

    $htmlFiles = page_builder_get_dir_html_files($themePath);

    $activeTheme = page_builder_get_settings()['landingpage'] ?? '';
    $activeThemeIndex = 0;
    foreach ($htmlFiles as $index => $file) {

        if (stripos($file, 'new-page-blank-template.html') !== false) continue; //skip template files
        $pathInfo = pathinfo($file);
        $extension = $pathInfo['extension'];
        if ($extension !== 'html') continue;

        $basePath = str_ireplace($themePath, '', $pathInfo['dirname']);
        $realFilename = $filename = $pathInfo['filename'];
        $paths = explode('/', trim($basePath, '/'));
        $folder = $paths[0];
        unset($paths[0]);
        $subfolder = join('/', $paths);

        if ($subfolder) {
            if ($folder !== $subfolder)
                $filename = $subfolder . '/' . $filename;
        }


        $url = str_ireplace($themePath, $themeUrl, $pathInfo['dirname'] . '/' . $pathInfo['basename']);

        $page = [
            "fullpath" => $file,
            "file" => str_ireplace($themePath, '', $file),
            "title" => ucfirst($filename),
            "url" => $url,
            "folder" => empty($folder) ? basename($subfolder) : $folder,
            "base_path_url" => str_ireplace(basename($realFilename) . '.' . $extension, '', $url)
        ];
        $pages[$index] = $page;

        if ($activeTheme == $page['file'])
            $activeThemeIndex = $index;
    }

    if ($activeThemeIndex) {
        // sort make acitve theme first one 
        $activeTheme = $pages[$activeThemeIndex];
        unset($pages[$activeThemeIndex]);
        $pages = array_merge([$activeTheme], $pages);
    }

    return $pages;
}

/**
 * Get parsed content of a page from path.
 * It add csrf to local form, parse assets files url to absolute.
 *
 * @param string $pagePath
 * @param boolean $redirect
 * @return string
 */
function page_builder_get_page_content($pagePath, $redirect = false)
{
    if ($redirect) {
        if (is_client_logged_in()) {
            return redirect('clients');
        }

        if (is_staff_logged_in()) {
            return redirect('admin');
        }
    }

    $page_body_content = file_get_contents($pagePath);

    $page_body_content = page_builder_parse_content_resources($page_body_content);

    return $page_body_content;
}

/**
 * Replace all non remove link in content with [PAGE_BUILDER_BASE_URL] tag
 *
 * @param string $html
 * @param string $path The relative folder path to the location of the html file content i.e /themename/somefolder/
 * @return string
 */
function page_builder_parse_content_resources($html, $path = '')
{
    $html = str_ireplace(['&#34;', '&#39;'], ['&quot;', '&apos;'], $html);

    // Replace URLs in HTML execpt for those starting with the tag or # or javascript:
    $html = preg_replace('/(href|src)=["\'](?:\.\.\/|\.\/)([^"\']+)["\']/', '$1="[PAGE_BUILDER_BASE_URL]/$2"', $html);
    $html = preg_replace('/(href|src)=["\']((?!https?:\/\/|\[PAGE_BUILDER_|#|javascript:|ftp:\/\/)[^"\']+)["\']/', '$1="[PAGE_BUILDER_BASE_URL]/$2"', $html);

    // Replace URLs in HTML execpt for those starting with the tag or # or javascript: '$1$2[PAGE_BUILDER_BASE_URL]/$4$5'
    $html = preg_replace(
        '/(url\()(["\']|&quot;|&#34;|&apos;|&#39;)(?!https?:\/\/|ftp:\/\/|javascript:|#|\[PAGE_BUILDER_)(\.\.\/|\.\/)?(.*?)(["\']|&quot;|&#34;|&apos;|&#39;)(\))/',
        '$1$2[PAGE_BUILDER_BASE_URL]/$4$5$6',
        $html
    );

    $html = preg_replace_callback('/@import (?:url\()?["\']((?!https?:\/\/|\[PAGE_BUILDER_|#|javascript:).*?)["\']?\)?;/', function ($matches) {
        return '@import url(' . $matches[1] . ')';
    }, $html);

    // Apply path
    if (!empty($path)) {
        $path = trim($path, '/');
        $path = '/' . $path . '/';
        $html = str_replace('[PAGE_BUILDER_BASE_URL]/', '[PAGE_BUILDER_BASE_URL]' . $path, $html);
        $html = str_replace('[PAGE_BUILDER_BASE_URL]' . $path . ltrim($path, '/'), '[PAGE_BUILDER_BASE_URL]' . $path, $html);
    }

    // clean up
    $html = str_replace('[PAGE_BUILDER_BASE_URL]//', '[PAGE_BUILDER_BASE_URL]/', $html);
    return $html;
}

/**
 * Validate and serve page file name conotent.
 *
 * @param string $page The page file name path without full path i.e /landing.html relative to the pages base url.
 * @param string $builder The name of the builder . Optional
 * @param bool   $return If to return parsed string of the page content or not
 * @return string|void String is $return is TRUE
 */
function page_builder_serve_page($page, $builder = '', $return = false)
{
    list($pagePath, $pageUrl, $pageBaseFolderUrl) = page_builder_get_theme_path_url();

    // Clean page and remove subfolder in base part
    $parts = parse_url($pageUrl);
    $basePath = $parts['path'] ?? '';
    if (!empty($basePath) && str_starts_with($page, $basePath)) {
        $page = ltrim(substr($page, strlen($basePath)), '/');
    }

    // Detect theme name and append 
    $themeName = explode('/', trim(dirname($page), '/'))[0];
    if (!empty($themeName)) {
        $pageUrl .= '/' . $themeName;
        $pageBaseFolderUrl .= '/' . $themeName;
    }

    // Serve if the file exist as html
    if (str_ends_with($page, '.html')) {
        $pagePath = str_replace('//', '/', $pagePath . '/' . $page);
        if (file_exists($pagePath)) {

            // Module asset dir url
            $assetPath =  module_dir_url(PAGE_BUILDER_MODULE_NAME, 'assets');

            // Get the page content
            $pageContent = page_builder_get_page_content($pagePath);
            $pageContent = str_ireplace(['[PAGE_BUILDER_ASSET_BASE_URL]', '[PAGE_BUILDER_BASE_URL]'], [$assetPath, $pageBaseFolderUrl], $pageContent);
            $pageContent = page_builder_html_purify($pageContent);

            // Unwrap any body tag
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $pageContent, $matches);
            $pageContent = $matches[1] ?? $pageContent;

            // Locale
            $language = get_option('active_language');
            $locale = get_locale_key($language);

            // Add saved page metadaa and others to the header
            $metadata = page_builder_get_metadata($pagePath);
            $pageData = [
                '[PAGE_BUILDER_CONTENT]' => $pageContent,
                '[PAGE_BUILDER_PAGE_URL]' => $pageUrl,
                '[PAGE_BUILDER_BASE_URL]' => $pageBaseFolderUrl,
                '[PAGE_BUILDER_ASSET_BASE_URL]' => $assetPath,
                '[PAGE_BUILDER_LANG]' => $locale,
            ];

            $defaultValues = [
                '[PAGE_BUILDER_SEO_ROBOT]' => 'index, follow',
                '[PAGE_BUILDER_TITLE]' => get_option('company_name'),
            ];

            foreach (PAGE_BUILDER_TAGS as $tag => $value) {
                $tagValue = !empty($metadata[$tag]) ? $metadata[$tag] : ($defaultValues[$tag] ?? '');
                $pageData[$tag] = str_starts_with($value, '_') ? $tagValue : xss_clean($tagValue);
            }

            if (empty($pageData['[PAGE_BUILDER_FAVICON]'])) {
                $pageData['[PAGE_BUILDER_FAVICON]'] = base_url(protected_file_url_by_path(get_upload_path_by_type('company') . get_option('favicon')));
            }

            $pageData['[PAGE_BUILDER_CUSTOM_CSS]'] = strip_tags(html_entity_decode(page_builder_html_purify($pageData['[PAGE_BUILDER_CUSTOM_CSS]'], 'style')));
            $pageData['[PAGE_BUILDER_COMPONENTS_CSS]'] = strip_tags(html_entity_decode(page_builder_html_purify($pageData['[PAGE_BUILDER_COMPONENTS_CSS]'], 'style')));

            // Parse content with the default template
            $layout = $metadata['[PAGE_BUILDER_LAYOUT_TEMPLATE]'] ?? 'bootstrap';
            if (empty($layout)) $layout = 'bootstrap';

            $layout = file_get_contents(module_dir_path(PAGE_BUILDER_MODULE_NAME, 'views/pages_layout_templates/' . $layout . '.html'));

            $content = str_ireplace(
                array_keys($pageData),
                array_values($pageData),
                $layout
            );

            $content = str_ireplace(
                array_keys($pageData),
                array_values($pageData),
                $content
            );

            if ($return === true) return $content;
            echo $content;
            exit;
        }
    }
}

/**
 * Purify HTML content.
 * The sanitizer will adapt its rules to only allow elements that are valid inside the given parent element.
 * 
 * @param string $content
 * @param string $element The parent element.
 * @return string The cleaned safe HTML
 */
function page_builder_html_purify(string $content, $element = 'body')
{

    $config = new HtmlSanitizerConfig();

    if ($element === 'body') {

        $allowedHosts = page_builder_whitelisted_hosts();
        $allowedHosts[] = '[PAGE_BUILDER_BASE_URL]';

        $customAttributesSanitizer = new CustomAttributeSanitizer($content);

        $config = $config->allowSafeElements()
            ->allowLinkHosts($allowedHosts)
            ->allowMediaHosts($allowedHosts)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowElement('iframe', ['src', 'height', 'width', 'allowfullscreen'])
            ->allowElement('svg', ['height', 'width', 'viewbox', 'xmlns', 'preserveaspectratio', 'enable-background', 'x', 'y'])
            ->allowElement('path', ['d', 'transform', 'opacity', 'fill'])
            ->allowElement('g', ['id'])
            ->allowElement('form', ['action', 'method'])
            ->allowElement('input', ['name', 'placeholder', 'type', 'src', 'value'])
            ->allowElement('select', ['name'])
            ->allowElement('option', ['value', 'selected'])
            ->allowElement('textarea', ['name', 'placeholder', 'value'])
            ->allowElement('canvas', ['width', 'height'])
            ->withMaxInputLength(1024 * 1024 * 2)
            ->withAttributeSanitizer($customAttributesSanitizer);

        $globalAttributes = ['id', 'class', 'style'];

        // Common data attribute for bootstrap and custom components
        $extraAttributes = [
            'tabindex'
        ];
        $dataAttributes = array_merge((array)$customAttributesSanitizer->getSupportedAttributes(), $extraAttributes);

        foreach (array_merge($globalAttributes, $dataAttributes) as $attr) {
            $config = $config->allowAttribute($attr, '*');
        }
    }

    $htmlSanitizer = new HtmlSanitizer(
        $config
    );

    $safeContents = $htmlSanitizer->sanitizeFor($element, $content);

    return trim($safeContents);
}

/**
 * Get list of supported builders/editors
 *
 * @return array
 */
function page_builder_editors()
{
    $editors = [
        'vvvebjs' => ['name' => 'Vvveb', 'image' => 'https://www.vvveb.com/media/logo.png'],
        'grapejs' => ['name' => 'GrapeJs', 'image' => 'https://grapesjs.com/assets/images/logos/grapesjs-logo.svg']
    ];

    if (!file_exists(module_dir_path(PAGE_BUILDER_MODULE_NAME, 'views/builders/grapejs.php'))) {
        unset($editors['grapejs']);
    }

    return $editors;
}

/**
 * Extract all styles, scripts, title, and meta tags (inline and external)
 *
 * @param string $html
 * @return array
 */
function page_builder_extract_meta_content($html)
{
    $styles = [];
    $scripts = [];
    $titles = [];
    $metas = [];
    $compiled = [];

    libxml_use_internal_errors(true);

    // Create a DOMDocument object
    $dom = new DOMDocument();

    // Load HTML content, suppressing errors caused by malformed HTML
    @$dom->loadHTML($html);

    // Extract external stylesheets
    $links = $dom->getElementsByTagName('link');
    foreach ($links as $link) {
        if ($link->hasAttribute('rel') && $link->getAttribute('rel') == 'stylesheet' && $link->hasAttribute('href')) {
            $href = $link->getAttribute('href');
            $styles[] = $href;
            $compiled[] = '<link rel="stylesheet" href="' . $href . '" />';
        }
        if ($link->hasAttribute('rel')) {
            if (in_array($link->getAttribute('rel'), ['shortcut icon', 'icon', 'shortcut', 'favicon']))
                $metas['favicon'] = $link->getAttribute('href');
            if ($link->getAttribute('rel') == 'canonical')
                $metas['canonical'] = $link->getAttribute('href');
        }
    }

    // Extract inline styles
    $style_tags = $dom->getElementsByTagName('style');
    foreach ($style_tags as $style_tag) {
        $styles[] = $style_tag->nodeValue;
        $compiled[] = '<style>' . $style_tag->nodeValue . '</style>';
    }

    // Extract external scripts
    $script_tags = $dom->getElementsByTagName('script');
    foreach ($script_tags as $script_tag) {
        $type = '';
        if ($script_tag->hasAttribute('type'))
            $type =  ' type="' . $link->getAttribute('type') . '"';
        if ($script_tag->hasAttribute('src')) {
            $src = $script_tag->getAttribute('src');
            $scripts[] = $src;
            $compiled[] = '<script src="' . $src . '"' . $type . '></script>';
        } else {
            $scripts[] = $script_tag->nodeValue;
            $compiled[] = '<script' . $type . '>' . $script_tag->nodeValue . '</script>';
        }
    }

    // Extract title
    $title_tags = $dom->getElementsByTagName('title');
    foreach ($title_tags as $title_tag) {
        $titles[] = $title_tag->nodeValue;
    }

    // Extract meta tags
    $meta_tags = $dom->getElementsByTagName('meta');
    foreach ($meta_tags as $meta_tag) {
        $name = '';
        $content = '';
        if ($meta_tag->hasAttribute('property'))
            $name = $meta_tag->getAttribute('prooperty');
        else if ($meta_tag->hasAttribute('name'))
            $name = $meta_tag->getAttribute('name');
        if (empty($name)) continue;
        if ($meta_tag->hasAttribute('content'))
            $content = $meta_tag->getAttribute('content');

        $metas[$name] = $content;
    }

    return [
        'styles' => $styles,
        'scripts' => $scripts,
        'titles' => $titles,
        'metas' => $metas,
        'compiledCode' => implode("", $compiled),
    ];
}

/**
 * Import raw pages from a source dir into a new folder name in builder pages
 *
 * @param string $source_dir The directory where the pages will be copied from i.e temp_dir
 * @param string $dest_folder_or_file_name Parent folder name where the pages will be placed or a file name
 * @param bool $is_file specify if $dest_folder_or_file_name is a file or not
 * @return bool
 * @throws Exception
 */
function page_builder_import_pages_from_folder($source_dir, $dest_folder_or_file_name, $is_file = false)
{
    list($theme_base_dir, $theme_base_url, $theme_real_base_url) = page_builder_get_theme_path_url();

    $dest_folder_name = $is_file ? '' : $dest_folder_or_file_name;

    $dest_dir = rtrim($theme_base_dir . '/' . $dest_folder_name, '/');
    if ((!$is_file && is_dir($dest_dir)) || ($is_file && file_exists($dest_dir . '/' . $dest_folder_or_file_name)))
        throw new \Exception(_l('page_builder_template_name_taken'), 1);

    // Scan the extracted files and whitelist allowed file types
    $file_groups = array(
        'content' => array('html'),
        'assets' => array('css', 'js', 'json'), // CSS, JavaScript, and other asset files
        'images' => page_builder_allowed_image_extensions(), // Image files
        'fonts' => array('woff', 'woff2', 'ttf', 'otf', 'eot'), // Font files
        'media' => array('mp3', 'mp4', 'webm'), // Media files
    );

    // Flatten the array to get the list of allowed file types
    $allowed_file_types = array_merge(...array_values($file_groups));

    $files = get_dir_file_info($source_dir, false);
    foreach ($files as $file) {
        if (is_dir($file['server_path'])) continue;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array($ext, $allowed_file_types)) {
            // File type not allowed, delete the file
            if (!unlink($file['server_path'])) {
                throw new \Exception(_l('page_builder_builder_error_deleting_file', $file['server_path']), 1);
            }
        }

        if (in_array($ext, $file_groups['assets'])) {
            $content = file_get_contents($file['server_path']);
            $content = str_ireplace(
                ['[PAGE_BUILDER_BASE_URL]', '[PAGE_BUILDER_PAGE_URL]'],
                [
                    $theme_real_base_url . '/' . $dest_folder_name,
                    $theme_base_url . '/' . $dest_folder_name
                ],
                $content
            );
            file_put_contents($file['server_path'], $content);
        }
    }

    // Move the remaining files to the theme folder
    if (!is_dir($dest_dir))
        mkdir($dest_dir, 0777, true);

    xcopy($source_dir, $dest_dir);

    // Extract script and style and save to dangerous extra code meta
    $htmlFiles = page_builder_get_dir_html_files($dest_dir);
    if ($is_file)
        $htmlFiles = [$dest_dir . '/' . $dest_folder_or_file_name];

    foreach ($htmlFiles as $file) {

        $html = file_get_contents($file);
        $html = page_builder_parse_content_resources($html);

        $metaInfo = page_builder_extract_meta_content($html);
        $metadata = [
            '_dangerous_extra_custom_code' => $metaInfo['compiledCode'],
            'title' => $metaInfo['titles'][0] ?? '',
            'layout_template' => 'vanilla',
        ];
        foreach (PAGE_BUILDER_TAGS as $key => $value) {
            if (str_starts_with($value, '_')) continue;
            if (isset($metaInfo['metas'][$value]))
                $metadata[$value] = $metaInfo['metas'][$value];
        }

        // Update page metadata
        page_builder_metadata($file, $metadata);

        $html = page_builder_serve_page(str_ireplace($theme_base_dir, '', $file), '', true);
        $sanitized = page_builder_html_purify($html);
        preg_match('/<body[^>]*>(.*?)<\/body>/is', $sanitized, $matches);
        $sanitized = $matches[1] ?? $sanitized;

        file_put_contents($file, trim($sanitized));
    }

    return true;
}

function page_builder_allowed_image_extensions()
{
    return ['ico', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
}

function page_builder_create_temp_dir($path = '')
{
    $temp_dir =  get_temp_dir() . bin2hex(random_bytes(20)) . '/' . trim($path, '/') . '/';
    if (!is_dir($temp_dir))
        mkdir($temp_dir, 0777, true);
    return $temp_dir;
}

function page_builder_maybe_redirect_to_dashboard($options = [])
{
    $client_logged_in = is_client_logged_in();
    $staff_logged_in = is_staff_logged_in();

    if (!$client_logged_in && !$staff_logged_in) return;

    $options = empty($options) ? page_builder_get_settings() : $options;

    if (!isset($options['redirect_to_dashboard'])) return;

    // Auto redirect to dashboard when loggedin in
    if ($options['redirect_to_dashboard'] != 'yes') return;

    if ($client_logged_in) {
        redirect(base_url('clients'));
    }

    if ($staff_logged_in && !is_admin()) {
        redirect(admin_url('dashboard'));
    }
}
