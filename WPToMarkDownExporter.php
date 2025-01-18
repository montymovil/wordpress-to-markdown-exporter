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
    // Verificar nonce
    if (
        !isset($_POST['exportar_markdown_nonce_field']) ||
        !wp_verify_nonce($_POST['exportar_markdown_nonce_field'], 'exportar_markdown_nonce')
    ) {
        wp_die('Verificación de seguridad fallida');
    }

    // Obtener todos los posts y páginas
    $args = array(
        'post_type'   => array('post', 'page'),
        'post_status' => 'publish',
        'numberposts' => -1
    );

    $posts = get_posts($args);

    if (empty($posts)) {
        echo '<div class="notice notice-warning"><p>No se encontraron artículos o páginas para exportar.</p></div>';
        return;
    }

    // Obtener todas las categorías
    $categories = get_categories(array(
        'hide_empty' => false,
    ));

    // Crear una carpeta para los archivos exportados
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/export_markdown';

    // Definir subdirectorios
    $images_dir      = $export_dir . '/assets/images';
    $pages_dir       = $export_dir . '/pages';
    $posts_dir       = $export_dir . '/posts';
    $categories_dir  = $export_dir . '/categories';

    // Crear los directorios si no existen
    foreach (array($export_dir, $images_dir, $pages_dir, $posts_dir, $categories_dir) as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Procesar y exportar posts y páginas
    foreach ($posts as $post) {
        // Determinar si es una página o un post
        $post_type = $post->post_type;

        // Obtener el contenido y aplicar filtros
        $content = apply_filters('the_content', $post->post_content);

        // Procesar imágenes y obtener la ruta de la imagen principal
        $image_relative_path = '';
        $content = procesar_imagenes_markdown($content, $images_dir, $post->ID, $image_relative_path);
        $featured_img = get_the_post_thumbnail_url($post->ID, 'full');

        // Obtener información adicional
        $title        = $post->post_title;
        $author_id    = $post->post_author;
        $author_name  = get_the_author_meta('display_name', $author_id);
        $excerpt      = $post->post_excerpt;
        $publish_date = get_the_date('c', $post->ID); // Formato ISO 8601

        // Obtener categorías y etiquetas (solo para posts)
        if ($post_type === 'post') {
            $post_categories = get_the_category($post->ID);
            $tags            = get_the_tags($post->ID);

            $category_names = array();
            if ($post_categories) {
                foreach ($post_categories as $category) {
                    $category_names[] = $category->name;
                }
            }

            $tag_names = array();
            if ($tags) {
                foreach ($tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }
        }

        // Determinar el directorio de destino
        if ($post_type === 'page') {
            $dest_dir = $pages_dir;
        } else {
            $dest_dir = $posts_dir;
        }

        // Crear un nombre de archivo basado en el título del post
        $filename = sanitize_title($title) . '.md';
        $file_path = $dest_dir . '/' . $filename;

        // Construir el front matter YAML siguiendo el formato proporcionado
        $yaml = "---\n";
        $yaml .= "publishDate: " . $publish_date . "\n";
        $yaml .= "author: " . addslashes($author_name) . "\n";
        $yaml .= "title: " . addslashes($title) . "\n";
        $yaml .= "excerpt: \"" . addslashes($excerpt) . "\"\n";

        // Manejar la imagen destacada si existe
        if ($featured_img) {
            // Copiar la imagen destacada si no se ha hecho ya
            $upload_dir_base = wp_upload_dir();
            $relative_path   = str_replace($upload_dir_base['baseurl'], '', $featured_img);
            $image_path      = $upload_dir_base['basedir'] . $relative_path;
            if (file_exists($image_path)) {
                $image_filename     = basename($image_path);
                $dest_image_path    = $images_dir . '/' . $image_filename;
                $image_export_path  = '~/assets/images/' . $image_filename;
                if (!file_exists($dest_image_path)) {
                    copy($image_path, $dest_image_path);
                }
                $yaml .= "image: " . $image_export_path . "\n";
            } else {
                $yaml .= "image: \"\"\n";
            }
        } elseif (!empty($image_relative_path)) {
            $yaml .= "image: " . $image_relative_path . "\n";
        } else {
            $yaml .= "image: \"\"\n";
        }

        // Agregar categoría y etiquetas solo para posts
        if ($post_type === 'post') {
            // Categoría (singular)
            if (!empty($category_names)) {
                // Asumiendo que cada post tiene una sola categoría principal
                // Puedes ajustar esto según tus necesidades
                $primary_category = $category_names[0];
                $yaml .= "category: " . addslashes($primary_category) . "\n";
            } else {
                $yaml .= "category: \"\"\n";
            }

            // Etiquetas
            if (!empty($tag_names)) {
                $yaml .= "tags:\n";
                foreach ($tag_names as $tag) {
                    // Asegurar que cada etiqueta esté entre comillas
                    $yaml .= '  - "' . addslashes($tag) . '"' . "\n";
                }
            } else {
                $yaml .= "tags: []\n";
            }
        }

        // Agregar metadata.canonical
        $permalink = get_permalink($post->ID);
        $yaml .= "metadata:\n";
        $yaml .= "  canonical: \"" . addslashes($permalink) . "\"\n";

        $yaml .= "---\n\n";

        // Conversión básica de HTML a Markdown
        $markdown = html_a_markdown_basico($content);

        // Combinar YAML y contenido
        $full_markdown = $yaml . $markdown;

        // Guardar el archivo Markdown
        file_put_contents($file_path, $full_markdown);
    }

    // Procesar y exportar categorías
    foreach ($categories as $category) {
        // Crear un nombre de archivo basado en el nombre de la categoría
        $category_slug = sanitize_title($category->name);
        $filename      = $category_slug . '.md';
        $file_path     = $categories_dir . '/' . $filename;

        // Obtener posts en esta categoría
        $category_posts = get_posts(array(
            'category'    => $category->term_id,
            'post_type'   => array('post'),
            'post_status' => 'publish',
            'numberposts' => -1
        ));

        // Construir el front matter YAML para la categoría
        $yaml = "---\n";
        $yaml .= "publishDate: " . date('c') . "\n"; // Fecha actual
        $yaml .= "author: \"\"\n"; // No aplica para categorías
        $yaml .= "title: " . addslashes($category->name) . "\n";
        $yaml .= "excerpt: \"" . addslashes($category->description) . "\"\n";
        $yaml .= "image: \"\"\n"; // No aplica para categorías, pero puedes personalizarlo
        $yaml .= "category: \"\"\n"; // No aplica
        $yaml .= "tags: []\n"; // No aplica
        $yaml .= "metadata:\n";
        $yaml .= "  canonical: \"" . esc_url(get_category_link($category->term_id)) . "\"\n";
        $yaml .= "---\n\n";

        // Contenido de la categoría
        $content = "## Posts en la categoría \"" . $category->name . "\"\n\n";
        foreach ($category_posts as $post) {
            $post_title = $post->post_title;
            $post_slug  = sanitize_title($post->post_title);
            $post_path  = '../posts/' . $post_slug . '.md';
            $content    .= "- [" . $post_title . "](" . $post_path . ")\n";
        }

        // Combinar YAML y contenido
        $full_markdown = $yaml . $content;

        // Guardar el archivo Markdown de la categoría
        file_put_contents($file_path, $full_markdown);
    }

    echo '<div class="notice notice-success"><p>Exportación completada. Los archivos están en la carpeta <code>' . esc_html($export_dir) . '</code>.</p></div>';
}

function procesar_imagenes_markdown($content, $images_dir, $post_id, &$image_relative_path) {
    // Expresión regular para encontrar imágenes
    $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';

    return preg_replace_callback($pattern, function($matches) use ($images_dir, $post_id, &$image_relative_path) {
        $image_url = $matches[1];
        $upload_dir = wp_upload_dir();

        // Verificar si la imagen está en el directorio de uploads
        if (strpos($image_url, $upload_dir['baseurl']) !== false) {
            $relative_path = str_replace($upload_dir['baseurl'], '', $image_url);
            $image_path    = $upload_dir['basedir'] . $relative_path;

            if (file_exists($image_path)) {
                // Copiar la imagen al directorio de exportación
                $image_filename    = basename($image_path);
                $dest_path         = $images_dir . '/' . $image_filename;
                $image_export_path = '~/assets/images/' . $image_filename;

                if (!file_exists($dest_path)) {
                    copy($image_path, $dest_path);
                }

                // Definir la ruta relativa para el front matter
                $image_relative_path = $image_export_path;

                // Retornar la sintaxis de Markdown para la imagen
                return '![' . basename($image_filename) . '](' . $image_export_path . ')';
            }
        }

        // Si no se puede procesar la imagen, dejarla como está
        return $matches[0];
    }, $content);
}

function html_a_markdown_basico($html) {
    // Reemplazos básicos de HTML a Markdown
    $markdown = $html;

    // Encabezados usando preg_replace_callback para manejar str_repeat
    $markdown = preg_replace_callback('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', function($matches) {
        $level = intval($matches[1]); // Nivel del encabezado (1-6)
        $text  = trim($matches[2]);    // Texto del encabezado
        return str_repeat('#', $level) . ' ' . $text . "\n\n";
    }, $markdown);

    // Párrafos
    $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/i', '$1' . "\n\n", $markdown);

    // Enlaces
    $markdown = preg_replace('/<a href=["\']([^"\']+)["\']>(.*?)<\/a>/i', '[$2]($1)', $markdown);

    // Negritas y Cursivas
    $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $markdown);
    $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $markdown);

    // Listas Desordenadas
    $markdown = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) {
        $list = '';
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/i', $matches[1], $items)) {
            foreach ($items[1] as $item) {
                $item = trim($item);
                // Convertir cualquier etiqueta HTML dentro de los elementos de la lista
                $item = html_a_markdown_basico($item);
                $list .= '- ' . $item . "\n";
            }
        }
        return $list . "\n";
    }, $markdown);

    // Listas Ordenadas
    $markdown = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) {
        $list = '';
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/i', $matches[1], $items)) {
            $counter = 1;
            foreach ($items[1] as $item) {
                $item = trim($item);
                // Convertir cualquier etiqueta HTML dentro de los elementos de la lista
                $item = html_a_markdown_basico($item);
                $list .= $counter . '. ' . $item . "\n";
                $counter++;
            }
        }
        return $list . "\n";
    }, $markdown);

    // Saltos de línea
    $markdown = preg_replace('/<br\s*\/?>/i', "\n", $markdown);

    // Bloques de código
    $markdown = preg_replace_callback('/<pre><code[^>]*>(.*?)<\/code><\/pre>/is', function($matches) {
        $code = htmlspecialchars_decode(trim($matches[1]));
        return "```\n" . $code . "\n```\n\n";
    }, $markdown);

    // Tablas
    $markdown = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is', function($matches) {
        $table_html = $matches[1];
        // Convertir filas
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $table_html, $rows);
        $markdown_table = '';
        foreach ($rows[1] as $index => $row) {
            preg_match_all('/<(th|td)[^>]*>(.*?)<\/\1>/is', $row, $cells);
            $cell_texts = $cells[2];
            if ($index === 0) {
                // Encabezado
                $markdown_table .= '| ' . implode(' | ', $cell_texts) . " |\n";
                $markdown_table .= '| ' . str_repeat('--- | ', count($cell_texts)) . "\n";
            } else {
                $markdown_table .= '| ' . implode(' | ', $cell_texts) . " |\n";
            }
        }
        $markdown_table .= "\n";
        return $markdown_table;
    }, $markdown);

    // Eliminar etiquetas restantes
    $markdown = strip_tags($markdown);

    return $markdown;
}
?>
