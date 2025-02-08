<?php
/**
 * Plugin Name: WordPress to Markdown Exporter
 * Description: Exporta artículos, páginas, categorías y etiquetas a formato Markdown con encabezados YAML personalizados, separando medios en un directorio específico.
 * Version: 2.1
 * Author: Luis Ángel Montoya
 * Author URI: https://monty.pro
 * Plugin URI: https://monty.pro
 * Network: true
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
        $export_types = isset($_POST['export_types']) ? $_POST['export_types'] : array();
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;
        $export_structure = isset($_POST['export_structure']) ? sanitize_text_field($_POST['export_structure']) : 'default';
        update_option('markdown_export_batch_size', $batch_size);
        update_option('markdown_export_types', $export_types);
        update_option('markdown_export_structure', $export_structure);
        exportar_contenido_markdown($export_types, $batch_size, $export_structure);
    }

    $last_export_date = get_option('last_export_date', 'Nunca');
    $batch_size = get_option('markdown_export_batch_size', 10);
    $export_types = get_option('markdown_export_types', array('post', 'page', 'category', 'tag'));
    $export_structure = get_option('markdown_export_structure', 'default');

    ?>
    <div class="wrap">
        <h1>Exportar Contenido a Markdown</h1>
        <h2 class="nav-tab-wrapper">
            <a href="#export-settings" class="nav-tab nav-tab-active">Configuración</a>
            <a href="#export-progress" class="nav-tab">Progreso</a>
        </h2>

        <div id="export-settings" class="tab-content">
            <form method="post">
                <?php wp_nonce_field('exportar_markdown_nonce', 'exportar_markdown_nonce_field'); ?>
                <input type="hidden" name="exportar_markdown" value="1">
                <p>Última exportación: <strong><?php echo esc_html($last_export_date); ?></strong></p>
                <h3>Seleccionar contenido a exportar:</h3>
                <p>
                    <label><input type="checkbox" name="export_types[]" value="post" <?php checked(in_array('post', $export_types)); ?>> Posts</label><br>
                    <label><input type="checkbox" name="export_types[]" value="page" <?php checked(in_array('page', $export_types)); ?>> Páginas</label><br>
                    <label><input type="checkbox" name="export_types[]" value="category" <?php checked(in_array('category', $export_types)); ?>> Categorías</label><br>
                    <label><input type="checkbox" name="export_types[]" value="tag" <?php checked(in_array('tag', $export_types)); ?>> Etiquetas</label>
                </p>
                <h3>Tamaño del lote:</h3>
                <p>
                    <input type="number" name="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1">
                    <small>Número de posts a procesar por lote.</small>
                </p>
                <h3>Estructura de exportación:</h3>
                <p>
                    <select name="export_structure">
                        <option value="default" <?php selected($export_structure, 'default'); ?>>Por defecto</option>
                        <option value="flat" <?php selected($export_structure, 'flat'); ?>>Plana</option>
                        <option value="year-month" <?php selected($export_structure, 'year-month'); ?>>Por año/mes</option>
                    </select>
                </p>
                <p><input type="submit" class="button button-primary" value="Exportar a Markdown"></p>
            </form>
        </div>

        <div id="export-progress" class="tab-content" style="display:none;">
            <p>Exportando... <span id="export-progress-count">0</span> de <span id="export-total-count">0</span> posts procesados.</p>
            <div id="export-progress-bar" style="width:100%;background-color:#f1f1f1;">
                <div id="export-progress-bar-inner" style="width:0%;height:30px;background-color:#4caf50;"></div>
            </div>
            <button id="cancel-export" class="button button-secondary" style="display:none;">Cancelar Exportación</button>
            <div id="export-status-messages"></div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Manejar pestañas
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.tab-content').hide();
            $($(this).attr('href')).show();
        });

        // Manejar el envío del formulario
        $('form').on('submit', function(e) {
            e.preventDefault();
            $('.nav-tab-wrapper a[href="#export-progress"]').click();
            $('#export-progress-bar-inner').css('width', '0%');
            $('#export-status-messages').html('');
            $('#cancel-export').show();
            exportBatch(0);
        });

        // Manejar la cancelación de la exportación
        $('#cancel-export').on('click', function() {
            $('#export-status-messages').html('<p>Exportación cancelada.</p>');
            $('#cancel-export').hide();
        });

        function exportBatch(offset) {
            $.post(ajaxurl, {
                action: 'export_markdown_batch',
                offset: offset,
                _ajax_nonce: '<?php echo wp_create_nonce('export_markdown_batch_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var progress = (response.data.processed / response.data.total) * 100;
                    $('#export-progress-count').text(response.data.processed);
                    $('#export-total-count').text(response.data.total);
                    $('#export-progress-bar-inner').css('width', progress + '%');
                    $('#export-status-messages').html('<p>Exportando... ' + response.data.processed + ' de ' + response.data.total + ' posts procesados.</p>');
                    if (response.data.offset < response.data.total) {
                        exportBatch(response.data.offset);
                    } else {
                        $('#export-status-messages').html('<p>Exportación completada. Los archivos están en la carpeta <code><?php echo esc_html(wp_upload_dir()['basedir'] . '/export_markdown'); ?></code>.</p>');
                        $('#cancel-export').hide();
                    }
                } else {
                    $('#export-status-messages').html('<p>Error: ' + response.data + '</p>');
                    $('#cancel-export').hide();
                }
            });
        }
    });
    </script>
    <style>
    .nav-tab-wrapper { margin-bottom: 20px; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    #export-progress-bar { margin-top: 10px; }
    #export-progress-bar-inner { transition: width 0.5s; }
    #export-status-messages { margin-top: 10px; }
    </style>
    <?php
}

function exportar_contenido_markdown($export_types, $batch_size, $export_structure) {
    // No se necesita hacer nada aquí, ya que la exportación se maneja por lotes via AJAX
}

add_action('wp_ajax_export_markdown_batch', 'export_markdown_batch');

function export_markdown_batch() {
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();

    // Verificar nonce
    if (!check_ajax_referer('export_markdown_batch_nonce', '_ajax_nonce', false)) {
        wp_send_json_error('Verificación de seguridad fallida');
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = get_option('markdown_export_batch_size', 10);
    $export_types = get_option('markdown_export_types', array('post', 'page', 'category', 'tag'));
    $export_structure = get_option('markdown_export_structure', 'default');
    $last_export_date = get_option('last_export_date', '1970-01-01 00:00:00');

    // Crear carpetas con WP_Filesystem
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/export_markdown';
    $media_dir = $export_dir . '/assets/media';
    $pages_dir = $export_dir . '/pages';
    $posts_dir = $export_dir . '/posts';
    $categories_dir = $export_dir . '/categories';
    $tags_dir = $export_dir . '/tags';

    foreach (array($export_dir, $media_dir, $pages_dir, $posts_dir, $categories_dir, $tags_dir) as $dir) {
        if (!wp_mkdir_p($dir)) {
            wp_send_json_error('No se pudo crear el directorio: ' . esc_html($dir));
        }
    }

    // Obtener posts y páginas modificados desde la última exportación
    $args = array(
        'post_type' => in_array('post', $export_types) ? 'post' : '',
        'post_status' => 'publish',
        'numberposts' => $batch_size,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC',
        'date_query' => array(
            array(
                'column' => 'post_modified',
                'after' => $last_export_date,
            ),
        ),
    );
    $posts = get_posts($args);

    // Procesar y exportar posts y páginas
    foreach ($posts as $post) {
        $content = apply_filters('the_content', $post->post_content);
        $title = $post->post_title;
        $publish_date = gmdate('c', strtotime($post->post_date));
        $excerpt = wp_strip_all_tags($post->post_excerpt);

        // Exportar medios y actualizar rutas
        $content = exportar_medios($content, $media_dir);

        $yaml = "---\n";
        $yaml .= "publishDate: {$publish_date}\n";
        $yaml .= "title: " . addslashes($title) . "\n";
        $yaml .= "excerpt: " . addslashes($excerpt) . "\n";

        // Exportar campos personalizados (ACF)
        if (function_exists('get_fields')) {
            $fields = get_fields($post->ID);
            if ($fields) {
                foreach ($fields as $key => $value) {
                    $yaml .= $key . ": " . addslashes($value) . "\n";
                }
            }
        }

        $yaml .= "---\n\n";

        $markdown_content = $yaml . wp_strip_all_tags($content);

        $dest_dir = $post->post_type === 'page' ? $pages_dir : $posts_dir;
        $file_path = $dest_dir . '/' . sanitize_title($title) . '.md';

        if (!$wp_filesystem->put_contents($file_path, $markdown_content, FS_CHMOD_FILE)) {
            wp_send_json_error('Error al escribir el archivo: ' . esc_html($file_path));
        }
    }

    // Exportar categorías
    if (in_array('category', $export_types)) {
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

            if (!$wp_filesystem->put_contents($file_path, $markdown_content, FS_CHMOD_FILE)) {
                wp_send_json_error('Error al escribir el archivo: ' . esc_html($file_path));
            }
        }
    }

    // Exportar etiquetas
    if (in_array('tag', $export_types)) {
        $tags = get_tags(array('hide_empty' => false));
        foreach ($tags as $tag) {
            $yaml = "---\n";
            $yaml .= "title: " . addslashes($tag->name) . "\n";
            $yaml .= "excerpt: " . addslashes($tag->description) . "\n";
            $yaml .= "---\n\n";

            $content = "## Posts con la etiqueta \"{$tag->name}\"\n\n";
            $posts_in_tag = get_posts(array(
                'tag_id' => $tag->term_id,
                'post_status' => 'publish',
                'numberposts' => -1
            ));
            foreach ($posts_in_tag as $post) {
                $content .= "- [{$post->post_title}](../posts/" . sanitize_title($post->post_title) . ".md)\n";
            }

            $markdown_content = $yaml . $content;
            $file_path = $tags_dir . '/' . sanitize_title($tag->name) . '.md';

            if (!$wp_filesystem->put_contents($file_path, $markdown_content, FS_CHMOD_FILE)) {
                wp_send_json_error('Error al escribir el archivo: ' . esc_html($file_path));
            }
        }
    }

    // Actualizar la fecha de la última exportación
    if ($offset + count($posts) >= $batch_size) {
        update_option('last_export_date', current_time('mysql'));
    }

    // Obtener el total de posts para calcular el progreso
    $total_posts = wp_count_posts()->publish + wp_count_posts('page')->publish;

    wp_send_json_success(array(
        'processed' => $offset + count($posts),
        'total' => $total_posts,
        'offset' => $offset + $batch_size
    ));
}

function exportar_medios($content, $media_dir) {
    $upload_dir = wp_upload_dir();
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    $media_elements = $dom->getElementsByTagName('img');

    foreach ($media_elements as $media) {
        $src = $media->getAttribute('src');
        $media_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $src);
        $media_name = basename($media_path);
        $new_media_path = $media_dir . '/' . $media_name;

        if (file_exists($media_path)) {
            if (!copy($media_path, $new_media_path)) {
                error_log('Error al copiar el medio: ' . $media_path);
            } else {
                $content = str_replace($src, './assets/media/' . $media_name, $content);
            }
        }
    }

    return $content;
}