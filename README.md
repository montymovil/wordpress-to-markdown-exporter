# WordPress to Markdown Exporter

**WordPress to Markdown Exporter** es un plugin para WordPress desarrollado por [Luis Ángel Montoya](https://monty.pro) que permite exportar los posts, páginas y categorías del sitio a archivos Markdown (.md) con encabezados YAML personalizados. El plugin organiza los archivos exportados en subdirectorios, separando imágenes, posts, páginas y categorías.

## Características

- Exporta **posts** y **páginas** con sus respectivos contenidos.
- Incluye **front matter YAML** con metadatos como:
  - Fecha de publicación (`publishDate`)
  - Autor (`author`)
  - Título (`title`)
  - Extracto (`excerpt`)
  - Imagen destacada (`image`)
  - Categoría principal (para posts)
  - Etiquetas (para posts)
  - URL canónica (`metadata.canonical`)
- Procesa y copia las imágenes referenciadas a un directorio específico (`assets/images`).
- Exporta las **categorías** con un listado de los posts asociados.
- Conversión básica de contenido HTML a Markdown, incluyendo:
  - Encabezados, párrafos, enlaces
  - Negritas y cursivas
  - Listas ordenadas y desordenadas
  - Bloques de código y tablas

## Cómo funciona

1. **Instalación y Activación:**
   - Coloca el archivo del plugin en el directorio `/wp-content/plugins/` de tu instalación de WordPress.
   - Activa el plugin desde el panel de administración de WordPress.

2. **Uso:**
   - En el panel de administración, encontrarás un nuevo elemento en el menú llamado **"Exportar Markdown"**.
   - Accede a esta sección y haz clic en el botón **"Exportar a Markdown"**.
   - El plugin procesará todos los posts, páginas y categorías publicadas, generando archivos Markdown en el directorio de cargas de WordPress (`wp-content/uploads/export_markdown`).

3. **Archivos Generados:**
   - **Posts:** Se guardan en la carpeta `posts` dentro del directorio de exportación.
   - **Páginas:** Se guardan en la carpeta `pages`.
   - **Categorías:** Se guardan en la carpeta `categories`.
   - **Imágenes:** Todas las imágenes referenciadas se copian a la carpeta `assets/images`.

## Consideraciones

- La función `html_a_markdown_basico` realiza una conversión básica de HTML a Markdown. Dependiendo de la complejidad del contenido, es posible que se requiera una conversión más avanzada.
- El plugin sobrescribe archivos si estos ya existen en las carpetas de exportación.
- Se recomienda realizar una copia de seguridad del sitio antes de realizar procesos de exportación.

## Contribuciones

Si deseas contribuir a este proyecto, por favor sigue los siguientes pasos:

1. Realiza un fork del repositorio.
2. Crea una rama para tu nueva funcionalidad (`git checkout -b feature/nueva-funcionalidad`).
3. Realiza los cambios necesarios y realiza commit.
4. Envía un pull request explicando los cambios realizados.

## Licencia

Este proyecto se distribuye bajo la Licencia [MIT](LICENSE).

## Enlaces Útiles

- [Sitio del desarrollador](https://monty.pro)
- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)

---

¡Esperamos que este plugin te sea de gran utilidad!
