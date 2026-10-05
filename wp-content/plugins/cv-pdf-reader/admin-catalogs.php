<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'cvpr2_catalog_admin_menu' );
add_action( 'admin_post_cvpr2_catalog_action', 'cvpr2_catalog_admin_action' );
add_action( 'admin_post_cvpr2_private_pdf', 'cvpr2_private_pdf_download' );
add_action( 'init', 'cvpr2_register_pdf_sitemap' );
add_filter( 'query_vars', 'cvpr2_pdf_sitemap_query_var' );
add_action( 'template_redirect', 'cvpr2_render_pdf_sitemap' );
add_filter( 'robots_txt', 'cvpr2_pdf_sitemap_robots', 20, 2 );
add_action( 'rest_api_init', 'cvpr2_register_catalog_rest' );

function cvpr2_catalog_paths() {
	$upload = wp_upload_dir();
	$public = wp_normalize_path( trailingslashit( $upload['basedir'] ) . 'catalogos' );
	$private = wp_normalize_path( trailingslashit( dirname( rtrim( ABSPATH, '/\\' ) ) ) . 'cv-private-catalogos' );
	return array(
		'public'      => $public,
		'public_url'  => trailingslashit( $upload['baseurl'] ) . 'catalogos',
		'private'     => $private,
	);
}

function cvpr2_catalog_ensure_dirs() {
	$paths = cvpr2_catalog_paths();
	foreach ( array( 'public', 'private' ) as $key ) {
		if ( ! is_dir( $paths[ $key ] ) && ! wp_mkdir_p( $paths[ $key ] ) ) {
			return new WP_Error( 'cvpr_dir', 'Não foi possível criar a pasta de catálogos.' );
		}
	}
	return $paths;
}

function cvpr2_catalog_safe_name( $name ) {
	$name = sanitize_file_name( (string) $name );
	if ( '' === $name ) {
		return '';
	}
	if ( ! preg_match( '/\.pdf$/i', $name ) ) {
		$name .= '.pdf';
	}
	return $name;
}

function cvpr2_catalog_resolve_file( $name, $visibility ) {
	$paths = cvpr2_catalog_paths();
	$name = cvpr2_catalog_safe_name( $name );
	$dir = ( 'hidden' === $visibility ) ? $paths['private'] : $paths['public'];
	if ( ! $name || ! $dir ) {
		return '';
	}
	$file = wp_normalize_path( trailingslashit( $dir ) . $name );
	$base = wp_normalize_path( trailingslashit( $dir ) );
	if ( 0 !== strpos( $file, $base ) ) {
		return '';
	}
	return $file;
}

function cvpr2_catalog_is_pdf_upload( $tmp_name, $original_name ) {
	if ( ! $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
		return false;
	}
	$check = wp_check_filetype_and_ext( $tmp_name, $original_name, array( 'pdf' => 'application/pdf' ) );
	return ! empty( $check['ext'] ) && 'pdf' === strtolower( $check['ext'] );
}

function cvpr2_catalog_admin_menu() {
	add_media_page(
		'Catálogos PDF',
		'Catálogos PDF',
		'manage_options',
		'cvpr2-catalogos',
		'cvpr2_catalog_admin_page'
	);
}

function cvpr2_catalog_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$paths = cvpr2_catalog_ensure_dirs();
	if ( is_wp_error( $paths ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $paths->get_error_message() ) . '</p></div>';
		return;
	}

	$message = isset( $_GET['cvpr_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['cvpr_msg'] ) ) : '';
	if ( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	$visible = cvpr2_catalog_list_files( $paths['public'] );
	$hidden  = cvpr2_catalog_list_files( $paths['private'] );
	?>
	<div class="wrap">
		<h1>Catálogos PDF</h1>
		<p>Gerir os PDFs usados pelo leitor da Chave Vertical. Catálogos ocultos ficam fora da pasta pública e não entram no sitemap.</p>

		<div class="card" style="max-width:900px;padding:20px;margin:20px 0;">
			<h2>Importar novo catálogo</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="cvpr2_catalog_action">
				<input type="hidden" name="catalog_action" value="upload">
				<?php wp_nonce_field( 'cvpr2_catalog_action', 'cvpr_nonce' ); ?>
				<input type="file" name="catalog_pdf" accept="application/pdf,.pdf" required>
				<label style="margin-left:12px;"><input type="checkbox" name="hidden" value="1"> Importar como oculto</label>
				<?php submit_button( 'Importar PDF', 'primary', 'submit', false ); ?>
			</form>
		</div>

		<?php cvpr2_catalog_render_table( 'Catálogos visíveis', $visible, 'visible' ); ?>
		<?php cvpr2_catalog_render_table( 'Catálogos ocultos (apenas administrador)', $hidden, 'hidden' ); ?>
	</div>
	<?php
}

function cvpr2_catalog_list_files( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$files = glob( trailingslashit( $dir ) . '*.pdf' );
	if ( ! is_array( $files ) ) {
		return array();
	}
	natcasesort( $files );
	return array_values( $files );
}

function cvpr2_catalog_render_table( $title, $files, $visibility ) {
	?>
	<h2 style="margin-top:30px;"><?php echo esc_html( $title ); ?> <span style="font-weight:400;">(<?php echo esc_html( count( $files ) ); ?>)</span></h2>
	<table class="widefat striped" style="max-width:1200px;">
		<thead><tr><th>Ficheiro</th><th>Tamanho</th><th>Estado</th><th style="width:520px;">Ações</th></tr></thead>
		<tbody>
		<?php if ( empty( $files ) ) : ?>
			<tr><td colspan="4">Nenhum catálogo.</td></tr>
		<?php else : foreach ( $files as $file ) :
			$name = basename( $file );
			$size = size_format( filesize( $file ) );
			$paths = cvpr2_catalog_paths();
			$public_url = trailingslashit( $paths['public_url'] ) . rawurlencode( $name );
			$private_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cvpr2_private_pdf&file=' . rawurlencode( $name ) ),
				'cvpr2_private_pdf_' . $name
			);
			?>
			<tr>
				<td><strong><?php echo esc_html( $name ); ?></strong></td>
				<td><?php echo esc_html( $size ); ?></td>
				<td><?php echo 'hidden' === $visibility ? '<span style="color:#a00;font-weight:700;">OCULTO</span>' : '<span style="color:#06752d;font-weight:700;">PÚBLICO / INDEXÁVEL</span>'; ?></td>
				<td>
					<?php if ( 'visible' === $visibility ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener">Abrir PDF</a>
					<?php else : ?>
						<a class="button button-small" href="<?php echo esc_url( $private_url ); ?>" target="_blank" rel="noopener">Abrir como admin</a>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:inline-flex;gap:5px;align-items:center;margin-left:5px;">
						<input type="hidden" name="action" value="cvpr2_catalog_action">
						<input type="hidden" name="catalog_action" value="replace">
						<input type="hidden" name="file" value="<?php echo esc_attr( $name ); ?>">
						<input type="hidden" name="visibility" value="<?php echo esc_attr( $visibility ); ?>">
						<?php wp_nonce_field( 'cvpr2_catalog_action', 'cvpr_nonce' ); ?>
						<input type="file" name="catalog_pdf" accept="application/pdf,.pdf" required style="max-width:180px;">
						<button class="button button-small">Substituir</button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;gap:5px;align-items:center;margin-left:5px;">
						<input type="hidden" name="action" value="cvpr2_catalog_action">
						<input type="hidden" name="catalog_action" value="rename">
						<input type="hidden" name="file" value="<?php echo esc_attr( $name ); ?>">
						<input type="hidden" name="visibility" value="<?php echo esc_attr( $visibility ); ?>">
						<?php wp_nonce_field( 'cvpr2_catalog_action', 'cvpr_nonce' ); ?>
						<input type="text" name="new_name" value="<?php echo esc_attr( $name ); ?>" style="max-width:170px;" required>
						<button class="button button-small">Mudar nome</button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:5px;">
						<input type="hidden" name="action" value="cvpr2_catalog_action">
						<input type="hidden" name="catalog_action" value="<?php echo 'hidden' === $visibility ? 'show' : 'hide'; ?>">
						<input type="hidden" name="file" value="<?php echo esc_attr( $name ); ?>">
						<input type="hidden" name="visibility" value="<?php echo esc_attr( $visibility ); ?>">
						<?php wp_nonce_field( 'cvpr2_catalog_action', 'cvpr_nonce' ); ?>
						<button class="button button-small"><?php echo 'hidden' === $visibility ? 'Mostrar' : 'Ocultar'; ?></button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:5px;" onsubmit="return confirm('Apagar definitivamente este catálogo?');">
						<input type="hidden" name="action" value="cvpr2_catalog_action">
						<input type="hidden" name="catalog_action" value="delete">
						<input type="hidden" name="file" value="<?php echo esc_attr( $name ); ?>">
						<input type="hidden" name="visibility" value="<?php echo esc_attr( $visibility ); ?>">
						<?php wp_nonce_field( 'cvpr2_catalog_action', 'cvpr_nonce' ); ?>
						<button class="button button-small button-link-delete">Apagar</button>
					</form>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
	<?php
}

function cvpr2_catalog_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissões.', 403 );
	}
	check_admin_referer( 'cvpr2_catalog_action', 'cvpr_nonce' );

	$paths = cvpr2_catalog_ensure_dirs();
	if ( is_wp_error( $paths ) ) {
		wp_die( esc_html( $paths->get_error_message() ) );
	}

	$action = isset( $_POST['catalog_action'] ) ? sanitize_key( wp_unslash( $_POST['catalog_action'] ) ) : '';
	$file = isset( $_POST['file'] ) ? cvpr2_catalog_safe_name( wp_unslash( $_POST['file'] ) ) : '';
	$visibility = ( isset( $_POST['visibility'] ) && 'hidden' === sanitize_key( wp_unslash( $_POST['visibility'] ) ) ) ? 'hidden' : 'visible';
	$message = 'Operação concluída.';

	if ( 'upload' === $action ) {
		if ( empty( $_FILES['catalog_pdf']['tmp_name'] ) || ! cvpr2_catalog_is_pdf_upload( $_FILES['catalog_pdf']['tmp_name'], $_FILES['catalog_pdf']['name'] ) ) {
			wp_die( 'O ficheiro enviado não é um PDF válido.' );
		}
		$name = cvpr2_catalog_safe_name( $_FILES['catalog_pdf']['name'] );
		$target_dir = ! empty( $_POST['hidden'] ) ? $paths['private'] : $paths['public'];
		$target = wp_normalize_path( trailingslashit( $target_dir ) . $name );
		if ( file_exists( $target ) ) {
			wp_die( 'Já existe um catálogo com esse nome. Use Substituir.' );
		}
		if ( ! move_uploaded_file( $_FILES['catalog_pdf']['tmp_name'], $target ) ) {
			wp_die( 'Não foi possível guardar o PDF.' );
		}
		@chmod( $target, 0644 );
		$message = 'Catálogo importado.';
	} elseif ( 'replace' === $action ) {
		$current = cvpr2_catalog_resolve_file( $file, $visibility );
		if ( ! $current || ! is_file( $current ) ) {
			wp_die( 'Catálogo não encontrado.' );
		}
		if ( empty( $_FILES['catalog_pdf']['tmp_name'] ) || ! cvpr2_catalog_is_pdf_upload( $_FILES['catalog_pdf']['tmp_name'], $_FILES['catalog_pdf']['name'] ) ) {
			wp_die( 'O ficheiro enviado não é um PDF válido.' );
		}
		$tmp_target = $current . '.new';
		if ( ! move_uploaded_file( $_FILES['catalog_pdf']['tmp_name'], $tmp_target ) ) {
			wp_die( 'Não foi possível guardar o novo PDF.' );
		}
		if ( ! rename( $tmp_target, $current ) ) {
			@unlink( $tmp_target );
			wp_die( 'Não foi possível substituir o catálogo.' );
		}
		@chmod( $current, 0644 );
		$message = 'Catálogo substituído.';
	} elseif ( 'rename' === $action ) {
		$current = cvpr2_catalog_resolve_file( $file, $visibility );
		$new_name = isset( $_POST['new_name'] ) ? cvpr2_catalog_safe_name( wp_unslash( $_POST['new_name'] ) ) : '';
		$target = cvpr2_catalog_resolve_file( $new_name, $visibility );
		if ( ! $current || ! is_file( $current ) || ! $target ) {
			wp_die( 'Nome ou catálogo inválido.' );
		}
		if ( file_exists( $target ) && $target !== $current ) {
			wp_die( 'Já existe um catálogo com esse nome.' );
		}
		if ( $target !== $current && ! rename( $current, $target ) ) {
			wp_die( 'Não foi possível mudar o nome.' );
		}
		$message = 'Nome alterado.';
	} elseif ( 'hide' === $action || 'show' === $action ) {
		$from_visibility = ( 'hide' === $action ) ? 'visible' : 'hidden';
		$to_visibility   = ( 'hide' === $action ) ? 'hidden' : 'visible';
		$current = cvpr2_catalog_resolve_file( $file, $from_visibility );
		$target  = cvpr2_catalog_resolve_file( $file, $to_visibility );
		if ( ! $current || ! is_file( $current ) || ! $target ) {
			wp_die( 'Catálogo não encontrado.' );
		}
		if ( file_exists( $target ) ) {
			wp_die( 'Já existe um catálogo com esse nome no destino.' );
		}
		if ( ! rename( $current, $target ) ) {
			wp_die( 'Não foi possível alterar a visibilidade.' );
		}
		$message = ( 'hide' === $action ) ? 'Catálogo ocultado e removido da área pública.' : 'Catálogo publicado novamente.';
	} elseif ( 'delete' === $action ) {
		$current = cvpr2_catalog_resolve_file( $file, $visibility );
		if ( ! $current || ! is_file( $current ) ) {
			wp_die( 'Catálogo não encontrado.' );
		}
		if ( ! unlink( $current ) ) {
			wp_die( 'Não foi possível apagar o catálogo.' );
		}
		$message = 'Catálogo apagado.';
	} else {
		wp_die( 'Ação inválida.' );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'cvpr2-catalogos', 'cvpr_msg' => rawurlencode( $message ) ), admin_url( 'upload.php' ) ) );
	exit;
}

function cvpr2_private_pdf_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissões.', 403 );
	}
	$name = isset( $_GET['file'] ) ? cvpr2_catalog_safe_name( wp_unslash( $_GET['file'] ) ) : '';
	check_admin_referer( 'cvpr2_private_pdf_' . $name );
	$file = cvpr2_catalog_resolve_file( $name, 'hidden' );
	if ( ! $file || ! is_file( $file ) || ! is_readable( $file ) ) {
		wp_die( 'Catálogo não encontrado.', 404 );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( basename( $file ) ) . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file );
	exit;
}

function cvpr2_register_pdf_sitemap() {
	add_rewrite_rule( '^catalogos-pdf-sitemap\.xml$', 'index.php?cvpr_pdf_sitemap=1', 'top' );
}

function cvpr2_pdf_sitemap_query_var( $vars ) {
	$vars[] = 'cvpr_pdf_sitemap';
	return $vars;
}

function cvpr2_render_pdf_sitemap() {
	if ( ! get_query_var( 'cvpr_pdf_sitemap' ) ) {
		return;
	}
	$paths = cvpr2_catalog_paths();
	$files = cvpr2_catalog_list_files( $paths['public'] );
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow', true );
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
	foreach ( $files as $file ) {
		$url = trailingslashit( $paths['public_url'] ) . rawurlencode( basename( $file ) );
		echo '<url><loc>' . esc_url( $url ) . '</loc><lastmod>' . esc_html( gmdate( 'c', filemtime( $file ) ) ) . '</lastmod></url>';
	}
	echo '</urlset>';
	exit;
}

function cvpr2_pdf_sitemap_robots( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	$sitemap = home_url( '/catalogos-pdf-sitemap.xml' );
	if ( false === strpos( $output, $sitemap ) ) {
		$output .= "\nSitemap: " . $sitemap . "\n";
	}
	return $output;
}

function cvpr2_pdf_reader_activate() {
	cvpr2_catalog_ensure_dirs();
	cvpr2_register_pdf_sitemap();
	flush_rewrite_rules();
}

function cvpr2_pdf_reader_deactivate() {
	flush_rewrite_rules();
}


function cvpr2_register_catalog_rest() {
	register_rest_route(
		'cv-pdf/v1',
		'/catalogos',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'cvpr2_catalog_rest_response',
			'permission_callback' => '__return_true',
		)
	);
}

function cvpr2_catalog_rest_response() {
	$paths = cvpr2_catalog_paths();
	$files = cvpr2_catalog_list_files( $paths['public'] );
	$items = array();

	foreach ( $files as $file ) {
		$name = basename( $file );
		$url  = trailingslashit( $paths['public_url'] ) . rawurlencode( $name );
		$label = function_exists( 'cvpr2_pdf_catalogos_auto_title_from_filename' )
			? cvpr2_pdf_catalogos_auto_title_from_filename( $name )
			: preg_replace( '/\.pdf$/i', '', $name );
		$title = function_exists( 'cvpr2_pdf_catalogos_auto_title_label' )
			? cvpr2_pdf_catalogos_auto_title_label( $label )
			: $label;
		$cover = function_exists( 'cvpr2_resolve_cover_url' ) ? cvpr2_resolve_cover_url( $url, '' ) : '';

		$items[] = array(
			'id'       => sanitize_key( sanitize_title( $label ) ),
			'name'     => $label,
			'title'    => $title,
			'filename' => $name,
			'url'      => esc_url_raw( $url ),
			'cover'    => esc_url_raw( $cover ),
			'modified' => gmdate( 'c', filemtime( $file ) ),
			'size'     => filesize( $file ),
		);
	}

	$response = rest_ensure_response(
		array(
			'ok'         => true,
			'count'      => count( $items ),
			'catalogs'   => $items,
			'sitemap'    => home_url( '/catalogos-pdf-sitemap.xml' ),
			'updated_at' => gmdate( 'c' ),
		)
	);
	$response->header( 'Cache-Control', 'public, max-age=300, s-maxage=300' );
	return $response;
}
