<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('page_builder_build_file_tree')) {
    /**
     * Builder tree view of flat array of files from a directory
     *
     * @param array $files
     * @return array
     */
    function page_builder_build_file_tree($files)
    {
        $fileTree = [];
        foreach ($files as $file) {
            $path = explode('/', trim($file['file'], '/'));
            $currentDir = &$fileTree;
            foreach ($path as $dir) {
                if (!isset($currentDir[$dir])) {
                    $currentDir[$dir] = [];
                }
                $currentDir = &$currentDir[$dir];
            }
            $currentDir = $file;
        }
        return $fileTree;
    }
}


if (!function_exists('page_builder_display_file_tree')) {
    /**
     * Show folders and file for the files tree
     *
     * @param array $files
     * @param string $html
     * @return string The html reperesentation of the tree view
     */
    function page_builder_display_file_tree($files, &$html)
    {
        foreach ($files as $key => $file) {
            $isFolder = is_array($file) && !isset($file['base_path_url']) && !isset($file['title']);
            if ($isFolder) {
                $folder = $key;
                $rand = 'r' . bin2hex(random_bytes(2)) . '_';
                $html .= '<li class="folder-toggle">';
                $html .= '<input type="checkbox" id="' . $rand . $folder . '">';
                $html .= '<label for="' . $rand . $folder . '" class="control-label"><i class="fa-regular fa-folder mright5"></i> <span>' . ucfirst($folder) . '</span> <span class="caret" aria-hidden="true"></span>';
                $html .= '</label>';
                $html .= '<ol>';

                // Recursively display sub-files and sub-folders
                page_builder_display_file_tree($file, $html);
                $html .= '</ol></li>';
            } else { // If it's a file
                $fileFullPath = $file['fullpath'];
                $html .= '<li class="file" data-url="' . $file['url'] . '" data-file="' . $file['file'] . '" data-options-key="' . hash("md5", $fileFullPath) . '">';
                $html .= '<label for="' . $file['file'] . '" class="control-label">';
                $html .= '<div class="title"><i class="fa-regular fa-file mright5"></i> <span>' . ucfirst(basename($file['title'])) . '</span></div>';
                $html .= '<div class="file-actions">';
                $html .= '<span class="tw-text-sm tw-hidden sm:tw-inline-flex text-default">' . date('M dS, Y, g:i a', filemtime($fileFullPath)) . '</span><span class="tw-mx-2 tw-text-sm"> ' . bytesToSize($fileFullPath) . '</span>';
                $html .= '<a href="' . $file['url'] . '" target="_blank" class="btn btn-primary btn-sm mright5" data-toggle="tooltip" title="' . _l('page_builder_builder_view_page') . '"><i class="fa fa-eye"></i></a>';
                $html .= '<button type="button" class="build btn btn-primary btn-sm mright5" data-toggle="tooltip" title="' . _l('page_builder_build_page') . '"><i class="fa fa-tools"></i></button>';
                $html .= '<button type="button" class="rename btn btn-primary btn-sm mright5" data-toggle="tooltip" title="' . _l('page_builder_builder_edit_page') . '"><i class="fa fa-edit"></i></button>';
                $html .= '<button type="button" class="duplicate btn btn-primary mright5" data-toggle="tooltip" title="' . _l('page_builder_duplicate') . '"><i class="fa fa-copy"></i></button>';
                $html .= '<button type="button" class="delete btn btn-danger btn-sm" data-toggle="tooltip" title="' . _l('delete') . '"><i class="fa fa-trash"></i></button>';
                $html .= '</div>';
                $html .= '<input type="checkbox" id="' . $file['file'] . '">';
                $html .= '</label>';
                $html .= '</li>';
            }
        }
    }
}
