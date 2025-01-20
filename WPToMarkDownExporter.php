<?php
/**
 * Plugin Name: WordPress to Markdown Exporter
 * Description: Exporta artículos, páginas y categorías a formato Markdown con encabezados YAML personalizados, separando imágenes en un directorio específico, páginas en otro, posts en otro y categorías en otro.
 * Version: 1.6
 * Author: Luis Ángel Montoya
 * Author URI: https://monty.pro
 * Plugin URI: https://monty.pro
 */

if (!defined('ABSPATH')) {
    exit; // Evita el acceso directo al archivo.
}

// Agregar un menú en el panel de administración
add_action('admin_menu', 'exportar_markdown_menu');

function exportar_markdown_menu() {
    add_menu_page(
        'Exportar a Markdown',
        'Exportar Markdown',
        'manage_options',
        'exportar-markdown',
        'exportar_markdown_pagina',
        'dashicons-download',
        20
    );
}

function exportar_markdown_pagina() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verificar si se ha enviado el formulario
    if (isset($_POST['exportar_markdown'])) {
        exportar_contenido_markdown();
    }

    ?>
    <div class="wrap">
        <h1>Exportar Contenido a Markdown</h1>
        <form method="post">
            <?php wp_nonce_field('exportar_markdown_nonce', 'exportar_markdown_nonce_field'); ?>
            <input type="hidden" name="exportar_markdown" value="1">
            <p>Haz clic en el botón para exportar todos los artículos, páginas y categorías a archivos Markdown con una estructura organizada.</p>
            <p><input type="submit" class="button button-primary" value="Exportar a Markdown"></p>
        </form>
    </div>
    <?php
}

function exportar_contenido_markdown() {
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();

    // Verificar nonce
    if (!check_admin_referer('exportar_markdown_nonce', 'exportar_markdown_nonce_field')) {
        wp_die('Verificación de seguridad fallida');
    }

    // Crear carpetas con WP_Filesystem
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/export_markdown';
    $images_dir = $export_dir . '/assets/images';
    $pages_dir = $export_dir . '/pages';
    $posts_dir = $export_dir . '/posts';
    $categories_dir = $export_dir . '/categories';

    foreach (array($export_dir, $images_dir, $pages_dir, $posts_dir, $categories_dir) as $dir) {
        wp_mkdir_p($dir);
    }

    // Obtener posts y páginas
    $args = array(
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'numberposts' => -1
    );
    $posts = get_posts($args);

    // Procesar y exportar posts y páginas
    foreach ($posts as $post) {
        $content = apply_filters('the_content', $post->post_content);
        $title = $post->post_title;
        $publish_date = gmdate('c', strtotime($post->post_date));
        $excerpt = wp_strip_all_tags($post->post_excerpt);

        $yaml = "---\n";
        $yaml .= "publishDate: {$publish_date}\n";
        $yaml .= "title: " . addslashes($title) . "\n";
        $yaml .= "excerpt: " . addslashes($excerpt) . "\n";
        $yaml .= "---\n\n";

        $markdown_content = $yaml . wp_strip_all_tags($content);

        $dest_dir = $post->post_type === 'page' ? $pages_dir : $posts_dir;
        $file_path = $dest_dir . '/' . sanitize_title($title) . '.md';

        $wp_filesystem->put_contents($file_path, $markdown_content, FS_CHMOD_FILE);
    }

    // Exportar categorías
    $categories = get_categories(array('hide_empty' => false));
    foreach ($categories as $category) {
        $yaml = "---\n";
        $yaml .= "title: " . addslashes($category->name) . "\n";
        $yaml .= "excerpt: " . addslashes($category->description) . "\n";
        $yaml .= "---\n\n";

        $content = "## Posts en la categoría \"{$category->name}\"\n\n";
        $posts_in_category = get_posts(array(
            'category' => $category->term_id,
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        foreach ($posts_in_category as $post) {
            $content .= "- [{$post->post_title}](../posts/" . sanitize_title($post->post_title) . ".md)\n";
        }

        $markdown_content = $yaml . $content;
        $file_path = $categories_dir . '/' . sanitize_title($category->name) . '.md';

        $wp_filesystem->put_contents($file_path, $markdown_content, FS_CHMOD_FILE);
    }

    echo '<div class="notice notice-success"><p>Exportación completada. Los archivos están en la carpeta <code>' . esc_html($export_dir) . '</code>.</p></div>';
}
