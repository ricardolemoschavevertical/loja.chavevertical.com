<?php
/**
 * Plugin Name: Chave Vertical PDF Reader
 * Description: Leitor e catálogo automático de PDFs da Chave Vertical.
 * Version: 1.8.0
 * Author: Chave Vertical
 */
defined( 'ABSPATH' ) || exit;

/**
 * CV PDF Reader - Snippet completo
 *
 * Usar no plugin Code Snippets SEM <?php no início.
 * Definir como: Run snippet everywhere.
 *
 * Shortcode recomendado:
 * [cv_pdf_card id="aircraft" url="https://chavevertical.com/wp-content/uploads/catalogos/Aircraft.pdf" title="Catálogo Aircraft" crop="all" pdf_download="admin" print="admin" quote="all" whatsapp_number="351914580410" quote_email="info@chavevertical.com" phone="914 580 410" website_url="https://chavevertical.com" telegram_url=""]
 *
 * Funcionalidades:
 * - Capa manual opcional, capa automática por pasta /capas/ e cache de capa no servidor criada pelo administrador.
 * - Leitor em modal.
 * - Ajuste inicial da página inteira ao ecrã disponível.
 * - Botões: ajustar página, ajustar largura, zoom + e zoom -.
 * - Page Up/Page Down, setas e scroll do rato para mudar de página.
 * - Texto selecionável no PDF, quando o PDF tem texto real.
 * - Pesquisa no PDF e pesquisa por link: ?cv_pdf=aircraft&q=compressor
 * - Barra superior com contactos destacados, links clicáveis e botões WhatsApp/Telegram.
 * - Botão "Solicitar cotação" com envio por WhatsApp ou Email, apenas com texto/página.
 * - Links diretos para site, email, telefone, WhatsApp e Telegram no topo.
 * - Recorte com quadrado persistente e ações: Copiar, Guardar PNG e Partilhar.
 * - Impressão com cabeçalho comercial.
 */

if ( ! defined( 'CVPR2_SNIPPET_VERSION' ) ) {
	define( 'CVPR2_SNIPPET_VERSION', '1.8.0' );
}

add_shortcode( 'cv_pdf_card', 'cvpr2_pdf_card_shortcode' );
add_action( 'wp_ajax_cvpr2_save_pdf_cover', 'cvpr2_save_pdf_cover_ajax' );

function cvpr2_pdf_card_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'              => '',
			'url'             => '',
			'title'           => '',
			'cover'           => '',
			'width'           => '360px',
			'pdf_download'    => 'admin', // admin, all, none
			'crop'            => 'admin', // admin, all, none
			'print'           => 'admin', // admin, all, none
			'quote'           => 'all',   // admin, all, none
			'whatsapp_number' => '351914580410',
			'quote_email'     => 'info@chavevertical.com',
			'phone'           => '914 580 410',
			'website_url'     => 'https://chavevertical.com',
			'telegram_url'    => '',
		),
		$atts,
		'cv_pdf_card'
	);

	$id     = sanitize_key( $atts['id'] );
	$url    = esc_url_raw( trim( (string) $atts['url'] ), array( 'http', 'https' ) );
	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

	if ( empty( $id ) || empty( $url ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return '';
	}

	$title = sanitize_text_field( $atts['title'] );

	if ( empty( $title ) ) {
		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$title = $path ? basename( $path ) : 'Abrir PDF';
	}

	$is_admin_user   = current_user_can( 'manage_options' );
	$width           = cvpr2_sanitize_css_size( $atts['width'], '360px' );
	$pdf_download    = cvpr2_permission_flag( $atts['pdf_download'], $is_admin_user, array( 'admin', 'all', 'none' ), 'admin' );
	$crop            = cvpr2_permission_flag( $atts['crop'], $is_admin_user, array( 'admin', 'all', 'none' ), 'admin' );
	$print           = cvpr2_permission_flag( $atts['print'], $is_admin_user, array( 'admin', 'all', 'none' ), 'admin' );
	$quote           = cvpr2_permission_flag( $atts['quote'], $is_admin_user, array( 'admin', 'all', 'none' ), 'all' );
	$whatsapp_number = preg_replace( '/\D+/', '', (string) $atts['whatsapp_number'] );
	$quote_email     = sanitize_email( $atts['quote_email'] );
	$phone_display   = sanitize_text_field( $atts['phone'] );
	$phone_digits    = preg_replace( '/\D+/', '', (string) $atts['phone'] );
	$website_url     = esc_url_raw( trim( (string) $atts['website_url'] ), array( 'http', 'https' ) );
	$telegram_url    = esc_url_raw( trim( (string) $atts['telegram_url'] ), array( 'http', 'https' ) );

	if ( empty( $whatsapp_number ) ) {
		$whatsapp_number = '351914580410';
	}

	if ( empty( $quote_email ) ) {
		$quote_email = 'info@chavevertical.com';
	}

	if ( empty( $phone_digits ) ) {
		$phone_digits = '914580410';
	}

	if ( empty( $phone_display ) ) {
		$phone_display = '914 580 410';
	}

	if ( empty( $website_url ) ) {
		$website_url = 'https://chavevertical.com';
	}

	$cover_url = cvpr2_resolve_cover_url( $url, $atts['cover'] );

	cvpr2_enqueue_assets();

	ob_start();
	?>
	<div
		class="cvpr-card"
		style="--cvpr-card-width: <?php echo esc_attr( $width ); ?>;"
		data-cv-pdf-id="<?php echo esc_attr( $id ); ?>"
		data-cv-pdf-url="<?php echo esc_url( $url ); ?>"
		data-cv-pdf-title="<?php echo esc_attr( $title ); ?>"
		data-cv-cover-url="<?php echo esc_url( $cover_url ); ?>"
		data-cv-cover-key="<?php echo esc_attr( cvpr2_get_cover_cache_key( $url ) ); ?>"
		data-cv-can-download-pdf="<?php echo esc_attr( $pdf_download ); ?>"
		data-cv-can-crop="<?php echo esc_attr( $crop ); ?>"
		data-cv-can-print="<?php echo esc_attr( $print ); ?>"
		data-cv-can-quote="<?php echo esc_attr( $quote ); ?>"
		data-cv-whatsapp-number="<?php echo esc_attr( $whatsapp_number ); ?>"
		data-cv-quote-email="<?php echo esc_attr( $quote_email ); ?>"
		data-cv-phone-display="<?php echo esc_attr( $phone_display ); ?>"
		data-cv-phone-digits="<?php echo esc_attr( $phone_digits ); ?>"
		data-cv-website-url="<?php echo esc_url( $website_url ); ?>"
		data-cv-telegram-url="<?php echo esc_url( $telegram_url ); ?>"
	>
		<button type="button" class="cvpr-card__open" aria-label="<?php echo esc_attr( sprintf( 'Abrir %s', $title ) ); ?>">
			<span class="cvpr-card__preview<?php echo ! empty( $cover_url ) ? ' cvpr-card__preview--has-cover' : ''; ?>">
				<?php if ( ! empty( $cover_url ) ) : ?>
					<img class="cvpr-card__cover-img" src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
				<?php endif; ?>
				<canvas class="cvpr-card__canvas"<?php echo ! empty( $cover_url ) ? ' hidden' : ''; ?>></canvas>
				<span class="cvpr-card__loading"<?php echo ! empty( $cover_url ) ? ' hidden' : ''; ?>>A carregar capa...</span>
				<span class="cvpr-card__error">Pré-visualização indisponível. Clique para abrir.</span>
			</span>

			<span class="cvpr-card__title"><?php echo esc_html( $title ); ?></span>
		</button>
	</div>
	<?php

	return ob_get_clean();
}

function cvpr2_resolve_cover_url( $pdf_url, $explicit_cover_url = '' ) {
	$explicit_cover_url = esc_url_raw( trim( (string) $explicit_cover_url ), array( 'http', 'https' ) );

	if ( ! empty( $explicit_cover_url ) ) {
		return $explicit_cover_url;
	}

	$auto_cover_url = cvpr2_get_auto_cover_url( $pdf_url );

	if ( ! empty( $auto_cover_url ) ) {
		return $auto_cover_url;
	}

	return cvpr2_get_server_cover_url( $pdf_url );
}

function cvpr2_get_auto_cover_url( $pdf_url ) {
	$pdf_path = cvpr2_pdf_url_to_local_path( $pdf_url );

	if ( empty( $pdf_path ) ) {
		return '';
	}

	$pdf_url_parts = wp_parse_url( $pdf_url );
	$pdf_url_path  = isset( $pdf_url_parts['path'] ) ? $pdf_url_parts['path'] : '';

	if ( empty( $pdf_url_path ) ) {
		return '';
	}

	$base_name = pathinfo( $pdf_path, PATHINFO_FILENAME );
	$dir_path  = trailingslashit( dirname( $pdf_path ) ) . 'capas';
	$dir_url   = trailingslashit( dirname( $pdf_url ) ) . 'capas';
	$exts      = array( 'jpg', 'jpeg', 'png', 'webp' );

	foreach ( $exts as $ext ) {
		$candidate = trailingslashit( $dir_path ) . $base_name . '.' . $ext;

		if ( file_exists( $candidate ) && is_readable( $candidate ) ) {
			return trailingslashit( $dir_url ) . rawurlencode( $base_name ) . '.' . $ext;
		}
	}

	return '';
}

function cvpr2_get_server_cover_url( $pdf_url ) {
	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		return '';
	}

	$cache_key  = cvpr2_get_cover_cache_key( $pdf_url );
	$cover_file = 'cover-' . $cache_key . '.jpg';
	$cover_path = trailingslashit( $upload_dir['basedir'] ) . 'cv-pdf-covers/' . $cover_file;
	$cover_url  = trailingslashit( $upload_dir['baseurl'] ) . 'cv-pdf-covers/' . $cover_file;

	if ( file_exists( $cover_path ) && filesize( $cover_path ) > 0 ) {
		return $cover_url . '?v=' . filemtime( $cover_path );
	}

	return '';
}

function cvpr2_get_cover_cache_key( $pdf_url ) {
	$pdf_path = cvpr2_pdf_url_to_local_path( $pdf_url );

	if ( ! empty( $pdf_path ) && file_exists( $pdf_path ) ) {
		$base = sanitize_title( pathinfo( $pdf_path, PATHINFO_FILENAME ) );
		$size = filesize( $pdf_path );

		/*
		 * Chave estável por catálogo, não por URL absoluto.
		 * Assim, se o mesmo PDF existir em dois caminhos diferentes com o mesmo nome e tamanho,
		 * reutiliza a mesma capa já guardada.
		 */
		return md5( 'local|' . $base . '|' . $size );
	}

	$url_parts = wp_parse_url( $pdf_url );
	$path      = isset( $url_parts['path'] ) ? $url_parts['path'] : '';
	$base      = sanitize_title( pathinfo( $path, PATHINFO_FILENAME ) );

	if ( empty( $base ) ) {
		$base = md5( $pdf_url );
	}

	return md5( 'remote|' . $base );
}

function cvpr2_pdf_url_to_local_path( $pdf_url ) {
	$url_parts  = wp_parse_url( $pdf_url );
	$home_parts = wp_parse_url( home_url( '/' ) );

	if ( empty( $url_parts['host'] ) || empty( $url_parts['path'] ) || empty( $home_parts['host'] ) ) {
		return '';
	}

	$url_host  = preg_replace( '/^www\./', '', strtolower( $url_parts['host'] ) );
	$home_host = preg_replace( '/^www\./', '', strtolower( $home_parts['host'] ) );

	if ( $url_host !== $home_host ) {
		return '';
	}

	$url_path  = urldecode( $url_parts['path'] );
	$home_path = isset( $home_parts['path'] ) ? trailingslashit( $home_parts['path'] ) : '/';

	if ( '/' !== $home_path && 0 === strpos( $url_path, $home_path ) ) {
		$url_path = substr( $url_path, strlen( $home_path ) );
	} else {
		$url_path = ltrim( $url_path, '/' );
	}

	$local_path = wp_normalize_path( trailingslashit( ABSPATH ) . ltrim( $url_path, '/' ) );
	$real_path  = realpath( $local_path );
	$root_path  = realpath( ABSPATH );

	if ( ! $real_path || ! $root_path ) {
		return '';
	}

	$real_path = wp_normalize_path( $real_path );
	$root_path = wp_normalize_path( $root_path );

	if ( 0 !== strpos( $real_path, $root_path ) ) {
		return '';
	}

	if ( ! file_exists( $real_path ) || ! is_file( $real_path ) ) {
		return '';
	}

	return $real_path;
}

function cvpr2_save_pdf_cover_ajax() {
	check_ajax_referer( 'cvpr2_save_pdf_cover', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Sem permissao.' ), 403 );
	}

	$pdf_url = isset( $_POST['pdf_url'] ) ? esc_url_raw( wp_unslash( $_POST['pdf_url'] ), array( 'http', 'https' ) ) : '';
	$image   = isset( $_POST['image'] ) ? (string) wp_unslash( $_POST['image'] ) : '';

	if ( empty( $pdf_url ) || empty( $image ) ) {
		wp_send_json_error( array( 'message' => 'Dados em falta.' ), 400 );
	}

	$pdf_path = cvpr2_pdf_url_to_local_path( $pdf_url );

	if ( empty( $pdf_path ) || ! is_readable( $pdf_path ) ) {
		wp_send_json_error( array( 'message' => 'PDF local invalido.' ), 400 );
	}

	if ( ! preg_match( '/^data:image\/(jpeg|jpg);base64,([A-Za-z0-9+\/]+=*)$/', $image, $matches ) ) {
		wp_send_json_error( array( 'message' => 'Formato de imagem invalido.' ), 400 );
	}

	$binary = base64_decode( $matches[2], true );

	if ( false === $binary || strlen( $binary ) < 1000 || strlen( $binary ) > 5 * 1024 * 1024 ) {
		wp_send_json_error( array( 'message' => 'Imagem invalida ou demasiado grande.' ), 400 );
	}

	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		wp_send_json_error( array( 'message' => 'Erro na pasta de uploads.' ), 500 );
	}

	$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'cv-pdf-covers';
	$cache_url = trailingslashit( $upload_dir['baseurl'] ) . 'cv-pdf-covers';

	if ( ! wp_mkdir_p( $cache_dir ) ) {
		wp_send_json_error( array( 'message' => 'Nao foi possivel criar a pasta de capas.' ), 500 );
	}

	$cover_file = 'cover-' . cvpr2_get_cover_cache_key( $pdf_url ) . '.jpg';
	$cover_path = trailingslashit( $cache_dir ) . $cover_file;
	$cover_url  = trailingslashit( $cache_url ) . $cover_file;

	if ( file_exists( $cover_path ) && filesize( $cover_path ) > 0 ) {
		wp_send_json_success(
			array(
				'url'     => $cover_url . '?v=' . filemtime( $cover_path ),
				'exists'  => true,
				'saved'   => false,
				'message' => 'A capa ja existia. Nao foi gravada novamente.',
			)
		);
	}

	$result = file_put_contents( $cover_path, $binary, LOCK_EX );

	if ( false === $result ) {
		wp_send_json_error( array( 'message' => 'Nao foi possivel guardar a capa.' ), 500 );
	}

	@chmod( $cover_path, 0644 );

	wp_send_json_success(
		array(
			'url'     => $cover_url . '?v=' . filemtime( $cover_path ),
			'exists'  => false,
			'saved'   => true,
			'message' => 'Capa criada e guardada.',
		)
	);
}

function cvpr2_permission_flag( $mode, $is_admin_user, $allowed_modes, $default_mode ) {
	$mode = sanitize_key( $mode );

	if ( ! in_array( $mode, $allowed_modes, true ) ) {
		$mode = $default_mode;
	}

	if ( 'all' === $mode ) {
		return '1';
	}

	if ( 'admin' === $mode && $is_admin_user ) {
		return '1';
	}

	return '0';
}

function cvpr2_sanitize_css_size( $value, $fallback = '360px' ) {
	$value = trim( (string) $value );

	if ( preg_match( '/^\d+(\.\d+)?(px|%|rem|em|vw)$/', $value ) ) {
		return $value;
	}

	return $fallback;
}

function cvpr2_enqueue_assets() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$loaded = true;

	wp_enqueue_script(
		'cvpr2-pdfjs',
		'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
		array(),
		'3.11.174',
		true
	);

	$worker_src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

	$js = <<<'JS'
(function () {
	'use strict';

	const state = {
		pdf: null,
		page: 1,
		total: 0,
		id: '',
		url: '',
		title: '',
		canDownloadPdf: false,
		canCrop: false,
		canPrint: false,
		canQuote: false,
		whatsappNumber: '351914580410',
		quoteEmail: 'info@chavevertical.com',
		phoneDisplay: '914 580 410',
		phoneDigits: '914580410',
		websiteUrl: 'https://chavevertical.com',
		telegramUrl: '',
		cropMode: false,
		cropStart: null,
		cropEnd: null,
		cropDragging: false,
		searchToken: 0,
		renderTask: null,
		zoomMode: 'fit-page',
		zoomScale: 1,
		minZoom: 0.35,
		maxZoom: 3
	};

	let resizeTimer = null;
	const coverSavePromises = {};
	let wheelNavLock = false;

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
		} else {
			callback();
		}
	}

	function esc(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function normalise(value) {
		return String(value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '');
	}

	function slugify(value) {
		const slug = String(value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');

		return slug || 'pdf';
	}

	function setWorker() {
		if (window.pdfjsLib && window.CVPR_CONFIG && window.CVPR_CONFIG.workerSrc) {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = window.CVPR_CONFIG.workerSrc;
		}
	}

	function findCard(id) {
		return document.querySelector('.cvpr-card[data-cv-pdf-id="' + CSS.escape(id) + '"]');
	}

	function getSelectedText() {
		const selection = window.getSelection ? window.getSelection().toString() : '';
		return String(selection || '').trim();
	}

	async function renderCover(card) {
		if (!card || card.dataset.cvCoverRendered === '1') {
			return;
		}

		card.dataset.cvCoverRendered = '1';

		const coverUrl = card.dataset.cvCoverUrl || '';
		const coverImg = card.querySelector('.cvpr-card__cover-img');
		const canvas = card.querySelector('.cvpr-card__canvas');
		const loading = card.querySelector('.cvpr-card__loading');
		const error = card.querySelector('.cvpr-card__error');

		if (coverImg) {
			if (coverImg.complete && coverImg.naturalWidth > 0 && loading) {
				loading.hidden = true;
			}

			coverImg.addEventListener('load', function () {
				if (loading) {
					loading.hidden = true;
				}
			}, { once: true });

			coverImg.addEventListener('error', function () {
				if (loading && !coverUrl) {
					loading.hidden = true;
				}
			}, { once: true });
		}

		if (coverUrl) {
			if (canvas) {
				canvas.hidden = true;
			}

			if (loading) {
				loading.hidden = true;
			}

			if (error) {
				error.style.display = 'none';
			}

			return;
		}

		const url = card.dataset.cvPdfUrl;
		const preview = card.querySelector('.cvpr-card__preview');

		if (!url || !canvas || !window.pdfjsLib) {
			if (loading) {
				loading.hidden = true;
			}

			if (error) {
				error.style.display = 'flex';
			}

			return;
		}

		try {
			const pdf = await window.pdfjsLib.getDocument({ url: url, withCredentials: false }).promise;
			const page = await pdf.getPage(1);
			const baseViewport = page.getViewport({ scale: 1 });
			const targetWidth = Math.max(preview.clientWidth || 360, 260);
			const viewport = page.getViewport({ scale: targetWidth / baseViewport.width });

			canvas.hidden = false;
			canvas.width = viewport.width;
			canvas.height = viewport.height;

			await page.render({
				canvasContext: canvas.getContext('2d'),
				viewport: viewport
			}).promise;

			canvas.style.display = 'block';

			if (loading) {
				loading.hidden = true;
			}

			saveCoverToServer(card, canvas);
		} catch (e) {
			if (loading) {
				loading.hidden = true;
			}

			if (error) {
				error.style.display = 'flex';
			}
		}
	}

	async function saveCoverToServer(card, canvas) {
		if (!window.CVPR_CONFIG || !window.CVPR_CONFIG.canSaveCovers || !window.CVPR_CONFIG.ajaxUrl || !window.CVPR_CONFIG.nonce) {
			return;
		}

		if (!card || !canvas) {
			return;
		}

		const pdfUrl = card.dataset.cvPdfUrl || '';
		const coverUrl = card.dataset.cvCoverUrl || '';
		const coverKey = card.dataset.cvCoverKey || pdfUrl;

		if (!pdfUrl || coverUrl) {
			return;
		}

		const lockKey = 'cvpr-cover-save-' + coverKey;

		if (coverSavePromises[lockKey]) {
			return coverSavePromises[lockKey];
		}

		if (sessionStorage.getItem(lockKey) === 'done') {
			return;
		}

		coverSavePromises[lockKey] = (async function () {
			try {
				const image = canvas.toDataURL('image/jpeg', 0.82);
				const formData = new FormData();

				formData.append('action', 'cvpr2_save_pdf_cover');
				formData.append('nonce', window.CVPR_CONFIG.nonce);
				formData.append('pdf_url', pdfUrl);
				formData.append('image', image);

				const response = await fetch(window.CVPR_CONFIG.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				});

				const result = await response.json();

				if (result && result.success && result.data && result.data.url) {
					card.dataset.cvCoverUrl = result.data.url;
					sessionStorage.setItem(lockKey, 'done');

					document.querySelectorAll('.cvpr-card[data-cv-cover-key="' + CSS.escape(coverKey) + '"]').forEach(function (sameCard) {
						sameCard.dataset.cvCoverUrl = result.data.url;

						const loading = sameCard.querySelector('.cvpr-card__loading');
						if (loading) {
							loading.hidden = true;
						}
					});
				}
			} catch (e) {
				sessionStorage.setItem(lockKey, 'failed');
			} finally {
				delete coverSavePromises[lockKey];
			}
		})();

		return coverSavePromises[lockKey];
	}

	function createModal() {
		if (document.getElementById('cvpr-modal')) {
			return;
		}

		const modal = document.createElement('div');
		modal.id = 'cvpr-modal';
		modal.className = 'cvpr-modal';
		modal.setAttribute('aria-hidden', 'true');

		modal.innerHTML =
			'<div class="cvpr-modal__dialog" role="dialog" aria-modal="true">' +
				'<div class="cvpr-contactbar">' +
					'<div class="cvpr-contactbar__info">' +
						'<a class="cvpr-contact-link cvpr-contact-site" href="https://chavevertical.com" target="_blank" rel="noopener noreferrer">🌐 CHAVEVERTICAL.COM</a>' +
						'<a class="cvpr-contact-link cvpr-contact-email" href="mailto:info@chavevertical.com">✉️ info@chavevertical.com</a>' +
						'<a class="cvpr-contact-link cvpr-contact-phone" href="tel:+351914580410">📞 914 580 410</a>' +
					'</div>' +
					'<div class="cvpr-contactbar__buttons">' +
						'<a class="cvpr-contact-button cvpr-contact-whatsapp" href="https://wa.me/351914580410" target="_blank" rel="noopener noreferrer">🟢 WhatsApp</a>' +
						'<a class="cvpr-contact-button cvpr-contact-telegram" href="https://t.me/share/url" target="_blank" rel="noopener noreferrer">✈️ Telegram</a>' +
						'<button type="button" class="cvpr-quote-open">Solicitar cotação</button>' +
					'</div>' +
				'</div>' +

				'<div class="cvpr-toolbar">' +
					'<div class="cvpr-toolbar__title"></div>' +
					'<form class="cvpr-search" role="search">' +
						'<input type="search" class="cvpr-search__input" placeholder="Pesquisar no PDF..." autocomplete="off">' +
						'<button type="submit" class="cvpr-search__button">Pesquisar</button>' +
					'</form>' +
					'<div class="cvpr-actions">' +
						'<button type="button" class="cvpr-prev">Anterior</button>' +
						'<span class="cvpr-page-info">Página 1</span>' +
						'<button type="button" class="cvpr-next">Seguinte</button>' +
						'<button type="button" class="cvpr-zoom-out" title="Reduzir zoom">−</button>' +
						'<span class="cvpr-zoom-info">Página inteira</span>' +
						'<button type="button" class="cvpr-zoom-in" title="Aumentar zoom">+</button>' +
						'<button type="button" class="cvpr-zoom-fit-page">Ajustar página</button>' +
						'<button type="button" class="cvpr-zoom-fit-width">Ajustar largura</button>' +
						'<button type="button" class="cvpr-print-page" hidden>Imprimir página</button>' +
						'<button type="button" class="cvpr-print-range" hidden>Imprimir páginas</button>' +
						'<button type="button" class="cvpr-copy-page" hidden>Copiar página</button>' +
						'<button type="button" class="cvpr-crop-start" hidden>Recortar</button>' +
						'<button type="button" class="cvpr-crop-copy" hidden disabled>Copiar seleção</button>' +
						'<button type="button" class="cvpr-crop-cancel" hidden>Cancelar</button>' +
						'<a class="cvpr-download-pdf" href="#" target="_blank" rel="noopener noreferrer" download hidden>Download PDF</a>' +
						'<button type="button" class="cvpr-close">Fechar</button>' +
					'</div>' +
				'</div>' +

				'<div class="cvpr-search-results" aria-live="polite"></div>' +

				'<div class="cvpr-canvas-wrap">' +
					'<div class="cvpr-canvas-stage">' +
						'<canvas class="cvpr-canvas"></canvas>' +
						'<div class="cvpr-text-layer"></div>' +
						'<div class="cvpr-crop-selection" hidden></div>' +
						'<div class="cvpr-crop-actions" hidden>' +
							'<button type="button" class="cvpr-crop-action-copy">Copiar</button>' +
							'<button type="button" class="cvpr-crop-action-save">Guardar PNG</button>' +
							'<button type="button" class="cvpr-crop-action-share">Partilhar</button>' +
						'</div>' +
						'<div class="cvpr-context-menu" hidden>' +
							'<button type="button" data-cvpr-menu-action="print-page">🖨️ Imprimir página</button>' +
							'<button type="button" data-cvpr-menu-action="print-range">🖨️ Imprimir páginas</button>' +
							'<button type="button" data-cvpr-menu-action="copy-page">📋 Copiar página</button>' +
							'<button type="button" data-cvpr-menu-action="crop-start">✂️ Recortar</button>' +
							'<hr>' +
							'<button type="button" data-cvpr-menu-action="quote-email">✉️ Solicitar contacto por Email</button>' +
							'<button type="button" data-cvpr-menu-action="quote-whatsapp">🟢 Solicitar contacto por WhatsApp</button>' +
							'<button type="button" data-cvpr-menu-action="quote-phone">📞 Solicitar contacto por Telefone</button>' +
						'</div>' +
					'</div>' +
				'</div>' +

				'<div class="cvpr-quote-panel" hidden>' +
					'<div class="cvpr-quote-panel__inner">' +
						'<div class="cvpr-quote-panel__head">' +
							'<strong>Solicitar cotação</strong>' +
							'<button type="button" class="cvpr-quote-close">×</button>' +
						'</div>' +
						'<p class="cvpr-quote-help">Selecione no catálogo a referência ou descrição do produto. Depois escolha WhatsApp ou Email.</p>' +
						'<div class="cvpr-quote-meta"></div>' +
						'<label>Texto selecionado / produto pretendido</label>' +
						'<textarea class="cvpr-quote-text" rows="5" placeholder="Selecione texto no PDF ou escreva aqui a referência/produto..."></textarea>' +
						'<label>Observações</label>' +
						'<textarea class="cvpr-quote-notes" rows="3">Pretendo receber preço e prazo de entrega.</textarea>' +
						'<div class="cvpr-quote-actions">' +
							'<button type="button" class="cvpr-quote-whatsapp">Enviar por WhatsApp</button>' +
							'<button type="button" class="cvpr-quote-email">Enviar por Email</button>' +
							'<button type="button" class="cvpr-quote-phone">Ligar por Telefone</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		document.body.appendChild(modal);

		modal.querySelector('.cvpr-close').addEventListener('click', closeReader);
		modal.querySelector('.cvpr-quote-open').addEventListener('click', openQuotePanel);
		modal.querySelector('.cvpr-quote-close').addEventListener('click', closeQuotePanel);
		modal.querySelector('.cvpr-quote-whatsapp').addEventListener('click', sendQuoteByWhatsapp);
		modal.querySelector('.cvpr-quote-email').addEventListener('click', sendQuoteByEmail);
		modal.querySelector('.cvpr-quote-phone').addEventListener('click', sendQuoteByPhone);

		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				closeReader();
			}
		});

		document.addEventListener('keydown', handleKeyboard);

		modal.querySelector('.cvpr-prev').addEventListener('click', function () {
			goToPage(state.page - 1);
		});

		modal.querySelector('.cvpr-next').addEventListener('click', function () {
			goToPage(state.page + 1);
		});

		modal.querySelector('.cvpr-zoom-in').addEventListener('click', zoomIn);
		modal.querySelector('.cvpr-zoom-out').addEventListener('click', zoomOut);
		modal.querySelector('.cvpr-zoom-fit-page').addEventListener('click', fitPage);
		modal.querySelector('.cvpr-zoom-fit-width').addEventListener('click', fitWidth);

		modal.querySelector('.cvpr-print-page').addEventListener('click', function () {
			printPages([state.page]);
		});

		modal.querySelector('.cvpr-print-range').addEventListener('click', promptPrintRange);

		modal.querySelector('.cvpr-search').addEventListener('submit', function (event) {
			event.preventDefault();

			const term = modal.querySelector('.cvpr-search__input').value.trim();

			updateUrlParams(state.id, term);
			searchInPdf(term);
		});

		modal.querySelector('.cvpr-copy-page').addEventListener('click', copyCurrentPageToClipboard);

		modal.querySelector('.cvpr-crop-start').addEventListener('click', function () {
			setCropMode(true);
		});

		modal.querySelector('.cvpr-crop-cancel').addEventListener('click', function () {
			setCropMode(false);
			clearCropSelection();
		});

		modal.querySelector('.cvpr-crop-copy').addEventListener('click', copySelectedAreaToClipboard);
		modal.querySelector('.cvpr-crop-action-copy').addEventListener('click', copySelectedAreaToClipboard);
		modal.querySelector('.cvpr-crop-action-save').addEventListener('click', saveSelectedAreaAsPng);
		modal.querySelector('.cvpr-crop-action-share').addEventListener('click', shareSelectedAreaWithSystem);

		const stage = modal.querySelector('.cvpr-canvas-stage');
		const canvasWrap = modal.querySelector('.cvpr-canvas-wrap');

		stage.addEventListener('pointerdown', startCropSelection);
		stage.addEventListener('pointermove', moveCropSelection);
		stage.addEventListener('pointerup', endCropSelection);
		stage.addEventListener('pointercancel', endCropSelection);

		canvasWrap.addEventListener('wheel', handleWheelNavigation, { passive: false });
		modal.addEventListener('contextmenu', handleCustomContextMenu);
		modal.querySelector('.cvpr-context-menu').addEventListener('click', handleContextMenuClick);
		document.addEventListener('click', function (event) {
			const menu = modal.querySelector('.cvpr-context-menu');

			if (menu && !menu.hidden && !event.target.closest('.cvpr-context-menu')) {
				hideContextMenu();
			}
		});
	}

	
	function handleCustomContextMenu(event) {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !modal.classList.contains('is-open')) {
			return;
		}

		if (!modal.contains(event.target)) {
			return;
		}

		if (
			event.target.closest('input') ||
			event.target.closest('textarea') ||
			event.target.closest('.cvpr-quote-panel')
		) {
			return;
		}

		event.preventDefault();
		showContextMenu(event.clientX, event.clientY);
	}

	function showContextMenu(clientX, clientY) {
		const modal = document.getElementById('cvpr-modal');
		const menu = modal ? modal.querySelector('.cvpr-context-menu') : null;

		if (!menu) {
			return;
		}

		setContextMenuItemVisibility(menu, 'print-page', state.canPrint);
		setContextMenuItemVisibility(menu, 'print-range', state.canPrint);
		setContextMenuItemVisibility(menu, 'copy-page', state.canCrop);
		setContextMenuItemVisibility(menu, 'crop-start', state.canCrop);
		setContextMenuItemVisibility(menu, 'quote-email', state.canQuote);
		setContextMenuItemVisibility(menu, 'quote-whatsapp', state.canQuote);
		setContextMenuItemVisibility(menu, 'quote-phone', true);

		menu.hidden = false;
		menu.style.left = '0px';
		menu.style.top = '0px';

		const rect = menu.getBoundingClientRect();
		const left = Math.max(8, Math.min(clientX, window.innerWidth - rect.width - 8));
		const top = Math.max(8, Math.min(clientY, window.innerHeight - rect.height - 8));

		menu.style.left = left + 'px';
		menu.style.top = top + 'px';
	}

	function setContextMenuItemVisibility(menu, action, visible) {
		const item = menu.querySelector('[data-cvpr-menu-action="' + action + '"]');

		if (item) {
			item.hidden = !visible;
		}
	}

	function hideContextMenu() {
		const modal = document.getElementById('cvpr-modal');
		const menu = modal ? modal.querySelector('.cvpr-context-menu') : null;

		if (menu) {
			menu.hidden = true;
		}
	}

	function handleContextMenuClick(event) {
		const button = event.target.closest('[data-cvpr-menu-action]');

		if (!button) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		const action = button.getAttribute('data-cvpr-menu-action');

		hideContextMenu();

		if (action === 'print-page') {
			printPages([state.page]);
			return;
		}

		if (action === 'print-range') {
			promptPrintRange();
			return;
		}

		if (action === 'copy-page') {
			copyCurrentPageToClipboard();
			return;
		}

		if (action === 'crop-start') {
			setCropMode(true);
			return;
		}

		if (action === 'quote-email') {
			prepareQuoteFromSelection();
			sendQuoteByEmail();
			return;
		}

		if (action === 'quote-whatsapp') {
			prepareQuoteFromSelection();
			sendQuoteByWhatsapp();
			return;
		}

		if (action === 'quote-phone') {
			prepareQuoteFromSelection();
			sendQuoteByPhone();
		}
	}

	function handleWheelNavigation(event) {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !modal.classList.contains('is-open') || !state.pdf) {
			return;
		}

		hideContextMenu();

		if (state.cropMode || !modal.querySelector('.cvpr-quote-panel').hidden) {
			return;
		}

		if (Math.abs(event.deltaY) < Math.abs(event.deltaX)) {
			return;
		}

		if (event.ctrlKey) {
			event.preventDefault();

			if (wheelNavLock) {
				return;
			}

			wheelNavLock = true;

			if (event.deltaY > 0) {
				zoomOut();
			} else {
				zoomIn();
			}

			window.setTimeout(function () {
				wheelNavLock = false;
			}, 120);

			return;
		}

		const wrap = event.currentTarget;
		const scrollingDown = event.deltaY > 0;
		const scrollingUp = event.deltaY < 0;
		const hasVerticalScroll = wrap.scrollHeight > wrap.clientHeight + 4;
		const atTop = wrap.scrollTop <= 2;
		const atBottom = wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 2;
		let targetPage = state.page;

		if (state.zoomMode === 'fit-page' || !hasVerticalScroll) {
			targetPage = scrollingDown ? state.page + 1 : state.page - 1;
		} else if (scrollingDown && atBottom) {
			targetPage = state.page + 1;
		} else if (scrollingUp && atTop) {
			targetPage = state.page - 1;
		} else {
			return;
		}

		if (targetPage < 1 || targetPage > state.total) {
			return;
		}

		event.preventDefault();

		if (wheelNavLock) {
			return;
		}

		wheelNavLock = true;
		goToPage(targetPage);

		window.setTimeout(function () {
			wheelNavLock = false;
		}, 420);
	}

	function handleKeyboard(event) {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !modal.classList.contains('is-open')) {
			return;
		}

		const active = document.activeElement;
		const isTyping =
			active &&
			(
				active.tagName === 'INPUT' ||
				active.tagName === 'TEXTAREA' ||
				active.isContentEditable
			);

		if (event.key === 'Escape') {
			event.preventDefault();

			if (!modal.querySelector('.cvpr-quote-panel').hidden) {
				closeQuotePanel();
			} else {
				closeReader();
			}

			return;
		}

		if (isTyping) {
			return;
		}

		if (event.key === 'PageDown' || event.key === 'ArrowRight') {
			event.preventDefault();
			goToPage(state.page + 1);
			return;
		}

		if (event.key === 'PageUp' || event.key === 'ArrowLeft') {
			event.preventDefault();
			goToPage(state.page - 1);
			return;
		}

		if (event.key === '+') {
			event.preventDefault();
			zoomIn();
			return;
		}

		if (event.key === '-') {
			event.preventDefault();
			zoomOut();
			return;
		}

		if (event.key === '0') {
			event.preventDefault();
			fitPage();
		}
	}

	function goToPage(page) {
		if (!state.pdf || page < 1 || page > state.total) {
			return;
		}

		renderPage(page);
	}

	function zoomIn() {
		state.zoomMode = 'manual';
		state.zoomScale = Math.min(state.maxZoom, state.zoomScale + 0.15);
		renderPage(state.page);
	}

	function zoomOut() {
		state.zoomMode = 'manual';
		state.zoomScale = Math.max(state.minZoom, state.zoomScale - 0.15);
		renderPage(state.page);
	}

	function fitPage() {
		state.zoomMode = 'fit-page';
		renderPage(state.page);
	}

	function fitWidth() {
		state.zoomMode = 'fit-width';
		renderPage(state.page);
	}

	function openModal() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal) {
			return;
		}

		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.documentElement.classList.add('cvpr-lock-scroll');
	}

	function closeReader() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal) {
			return;
		}

		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.documentElement.classList.remove('cvpr-lock-scroll');
		closeQuotePanel();
		hideContextMenu();
		setCropMode(false);
		clearCropSelection();
	}

	async function openReader(card, initialSearch) {
		if (!card) {
			return;
		}

		createModal();

		const modal = document.getElementById('cvpr-modal');

		state.pdf = null;
		state.page = 1;
		state.total = 0;
		state.id = card.dataset.cvPdfId || '';
		state.url = card.dataset.cvPdfUrl || '';
		state.title = card.dataset.cvPdfTitle || 'PDF';
		state.canDownloadPdf = card.dataset.cvCanDownloadPdf === '1';
		state.canCrop = card.dataset.cvCanCrop === '1';
		state.canPrint = card.dataset.cvCanPrint === '1';
		state.canQuote = card.dataset.cvCanQuote === '1';
		state.whatsappNumber = card.dataset.cvWhatsappNumber || '351914580410';
		state.quoteEmail = card.dataset.cvQuoteEmail || 'info@chavevertical.com';
		state.phoneDisplay = card.dataset.cvPhoneDisplay || '914 580 410';
		state.phoneDigits = card.dataset.cvPhoneDigits || '914580410';
		state.websiteUrl = card.dataset.cvWebsiteUrl || 'https://chavevertical.com';
		state.telegramUrl = card.dataset.cvTelegramUrl || '';
		state.zoomMode = 'fit-page';
		state.zoomScale = 1;
		state.searchToken++;

		setToolsVisibility();
		setContactLinks();
		clearCropSelection();
		setCropMode(false);

		modal.querySelector('.cvpr-toolbar__title').textContent = state.title;
		modal.querySelector('.cvpr-search__input').value = initialSearch || '';
		modal.querySelector('.cvpr-search-results').textContent = 'A carregar PDF...';
		modal.querySelector('.cvpr-zoom-info').textContent = 'Página inteira';

		const downloadPdf = modal.querySelector('.cvpr-download-pdf');

		if (state.canDownloadPdf) {
			downloadPdf.href = state.url;
			downloadPdf.setAttribute('download', slugify(state.title || 'catalogo') + '.pdf');
			downloadPdf.hidden = false;
		} else {
			downloadPdf.removeAttribute('href');
			downloadPdf.removeAttribute('download');
			downloadPdf.hidden = true;
		}

		openModal();

		try {
			state.pdf = await window.pdfjsLib.getDocument({
				url: state.url,
				withCredentials: false
			}).promise;

			state.total = state.pdf.numPages;

			await renderPage(1);

			if (initialSearch) {
				searchInPdf(initialSearch);
			} else {
				modal.querySelector('.cvpr-search-results').textContent = '';
			}
		} catch (e) {
			modal.querySelector('.cvpr-search-results').textContent = 'Não foi possível carregar o PDF.';
		}
	}

	function calculateScale(baseViewport, wrap) {
		const wrapWidth = Math.max(wrap.clientWidth - 40, 300);
		const wrapHeight = Math.max(wrap.clientHeight - 40, 300);
		let scale;

		if (state.zoomMode === 'fit-page') {
			scale = Math.min(
				wrapWidth / baseViewport.width,
				wrapHeight / baseViewport.height
			);
			state.zoomScale = scale;
		} else if (state.zoomMode === 'fit-width') {
			scale = wrapWidth / baseViewport.width;
			state.zoomScale = scale;
		} else {
			scale = state.zoomScale;
		}

		return Math.max(state.minZoom, Math.min(state.maxZoom, scale));
	}

	async function renderPage(pageNumber) {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !state.pdf) {
			return;
		}

		const canvas = modal.querySelector('.cvpr-canvas');
		const wrap = modal.querySelector('.cvpr-canvas-wrap');
		const textLayer = modal.querySelector('.cvpr-text-layer');
		const zoomInfo = modal.querySelector('.cvpr-zoom-info');

		try {
			if (state.renderTask && typeof state.renderTask.cancel === 'function') {
				try {
					state.renderTask.cancel();
				} catch (e) {}
			}

			const page = await state.pdf.getPage(pageNumber);
			const baseViewport = page.getViewport({ scale: 1 });
			const scale = calculateScale(baseViewport, wrap);
			const viewport = page.getViewport({ scale: scale });

			if (zoomInfo) {
				if (state.zoomMode === 'fit-page') {
					zoomInfo.textContent = 'Página inteira';
				} else if (state.zoomMode === 'fit-width') {
					zoomInfo.textContent = 'Largura';
				} else {
					zoomInfo.textContent = Math.round(scale * 100) + '%';
				}
			}

			canvas.width = viewport.width;
			canvas.height = viewport.height;
			canvas.style.width = viewport.width + 'px';
			canvas.style.height = viewport.height + 'px';

			if (textLayer) {
				textLayer.innerHTML = '';
				textLayer.style.width = viewport.width + 'px';
				textLayer.style.height = viewport.height + 'px';
				textLayer.style.setProperty('--scale-factor', viewport.scale);
			}

			state.renderTask = page.render({
				canvasContext: canvas.getContext('2d'),
				viewport: viewport
			});

			await state.renderTask.promise;

			if (textLayer) {
				try {
					const textContent = await page.getTextContent();

					if (typeof window.pdfjsLib.renderTextLayer === 'function') {
						const textTask = window.pdfjsLib.renderTextLayer({
							textContentSource: textContent,
							container: textLayer,
							viewport: viewport,
							textDivs: []
						});

						if (textTask && textTask.promise) {
							await textTask.promise;
						}
					}
				} catch (e) {}
			}

			const previousPage = state.page;

			state.page = pageNumber;
			modal.querySelector('.cvpr-page-info').textContent = 'Página ' + pageNumber + ' / ' + state.total;

			if (previousPage !== pageNumber) {
				wrap.scrollTop = 0;
				wrap.scrollLeft = 0;
			}

			hideContextMenu();
			clearCropSelection();
			setCropMode(false);
		} catch (e) {
			if (e && e.name === 'RenderingCancelledException') {
				return;
			}
		}
	}

	async function searchInPdf(term) {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !state.pdf) {
			return;
		}

		const resultsBox = modal.querySelector('.cvpr-search-results');
		const cleanTerm = String(term || '').trim();

		state.searchToken++;
		const thisSearch = state.searchToken;

		if (!cleanTerm) {
			resultsBox.textContent = '';
			return;
		}

		const needle = normalise(cleanTerm);
		const matches = [];

		resultsBox.innerHTML = 'A pesquisar por <strong>' + esc(cleanTerm) + '</strong>...';

		for (let pageNumber = 1; pageNumber <= state.total; pageNumber++) {
			if (thisSearch !== state.searchToken) {
				return;
			}

			try {
				const page = await state.pdf.getPage(pageNumber);
				const textContent = await page.getTextContent();
				const pageText = textContent.items.map(function (item) {
					return item.str || '';
				}).join(' ');

				if (normalise(pageText).indexOf(needle) !== -1) {
					matches.push(pageNumber);
				}
			} catch (e) {}

			if (pageNumber % 10 === 0) {
				resultsBox.innerHTML =
					'A pesquisar por <strong>' +
					esc(cleanTerm) +
					'</strong>... página ' +
					pageNumber +
					' / ' +
					state.total;
			}
		}

		if (thisSearch !== state.searchToken) {
			return;
		}

		if (!matches.length) {
			resultsBox.innerHTML = 'Sem resultados para: <strong>' + esc(cleanTerm) + '</strong>';
			return;
		}

		let html = '<span>Resultados para <strong>' + esc(cleanTerm) + '</strong>: </span>';

		matches.forEach(function (pageNumber) {
			html += '<button type="button" class="cvpr-result" data-page="' + pageNumber + '">Página ' + pageNumber + '</button>';
		});

		resultsBox.innerHTML = html;

		resultsBox.querySelectorAll('.cvpr-result').forEach(function (button) {
			button.addEventListener('click', function () {
				const page = parseInt(button.dataset.page, 10);

				if (page > 0) {
					renderPage(page);
				}
			});
		});

		renderPage(matches[0]);
	}

	function setToolsVisibility() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal) {
			return;
		}

		modal.querySelector('.cvpr-copy-page').hidden = !state.canCrop;
		modal.querySelector('.cvpr-crop-start').hidden = !state.canCrop;
		modal.querySelector('.cvpr-crop-copy').hidden = !state.canCrop;
		modal.querySelector('.cvpr-crop-cancel').hidden = !state.canCrop;
		modal.querySelector('.cvpr-crop-copy').disabled = true;
		modal.querySelector('.cvpr-crop-actions').hidden = true;
		modal.querySelector('.cvpr-print-page').hidden = !state.canPrint;
		modal.querySelector('.cvpr-print-range').hidden = !state.canPrint;
		modal.querySelector('.cvpr-quote-open').hidden = !state.canQuote;
	}

	function setCropMode(enabled) {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !state.canCrop) {
			return;
		}

		const stage = modal.querySelector('.cvpr-canvas-stage');

		state.cropMode = Boolean(enabled);

		if (stage) {
			stage.classList.toggle('is-cropping', state.cropMode);
		}
	}

	function clearCropSelection() {
		const modal = document.getElementById('cvpr-modal');

		state.cropStart = null;
		state.cropEnd = null;
		state.cropDragging = false;

		if (!modal) {
			return;
		}

		const selection = modal.querySelector('.cvpr-crop-selection');
		const cropCopy = modal.querySelector('.cvpr-crop-copy');
		const cropActions = modal.querySelector('.cvpr-crop-actions');

		if (selection) {
			selection.hidden = true;
			selection.style.left = '0px';
			selection.style.top = '0px';
			selection.style.width = '0px';
			selection.style.height = '0px';
		}

		if (cropCopy) {
			cropCopy.disabled = true;
		}

		if (cropActions) {
			cropActions.hidden = true;
		}
	}

	function getCropPoint(event, requireInside) {
		const modal = document.getElementById('cvpr-modal');
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;

		if (!canvas) {
			return null;
		}

		const rect = canvas.getBoundingClientRect();
		const rawX = event.clientX - rect.left;
		const rawY = event.clientY - rect.top;

		if (requireInside && (rawX < 0 || rawY < 0 || rawX > rect.width || rawY > rect.height)) {
			return null;
		}

		const cssX = Math.max(0, Math.min(rawX, rect.width));
		const cssY = Math.max(0, Math.min(rawY, rect.height));

		return {
			cssX: cssX,
			cssY: cssY,
			canvasX: cssX * (canvas.width / rect.width),
			canvasY: cssY * (canvas.height / rect.height)
		};
	}

	function startCropSelection(event) {
		if (!state.cropMode || !state.canCrop) {
			return;
		}

		if (event.target.closest && event.target.closest('.cvpr-crop-actions')) {
			return;
		}

		const point = getCropPoint(event, true);

		if (!point) {
			return;
		}

		event.preventDefault();

		state.cropStart = point;
		state.cropEnd = point;
		state.cropDragging = true;

		updateCropSelectionBox();

		const stage = document.querySelector('.cvpr-canvas-stage');

		if (stage && typeof stage.setPointerCapture === 'function') {
			try {
				stage.setPointerCapture(event.pointerId);
			} catch (e) {}
		}
	}

	function moveCropSelection(event) {
		if (!state.cropMode || !state.cropStart || !state.cropDragging) {
			return;
		}

		event.preventDefault();

		const point = getCropPoint(event, false);

		if (!point) {
			return;
		}

		state.cropEnd = point;
		updateCropSelectionBox();
	}

	function endCropSelection(event) {
		if (!state.cropMode || !state.cropStart || !state.cropDragging) {
			return;
		}

		event.preventDefault();

		const point = getCropPoint(event, false);

		if (point) {
			state.cropEnd = point;
		}

		state.cropDragging = false;
		updateCropSelectionBox();

		const stage = document.querySelector('.cvpr-canvas-stage');

		if (stage && typeof stage.releasePointerCapture === 'function') {
			try {
				stage.releasePointerCapture(event.pointerId);
			} catch (e) {}
		}
	}

	function updateCropSelectionBox() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !state.cropStart || !state.cropEnd) {
			return;
		}

		const selection = modal.querySelector('.cvpr-crop-selection');
		const cropCopy = modal.querySelector('.cvpr-crop-copy');
		const cropActions = modal.querySelector('.cvpr-crop-actions');

		if (!selection) {
			return;
		}

		const left = Math.min(state.cropStart.cssX, state.cropEnd.cssX);
		const top = Math.min(state.cropStart.cssY, state.cropEnd.cssY);
		const width = Math.abs(state.cropStart.cssX - state.cropEnd.cssX);
		const height = Math.abs(state.cropStart.cssY - state.cropEnd.cssY);
		const disabled = width < 8 || height < 8;

		selection.hidden = false;
		selection.style.left = left + 'px';
		selection.style.top = top + 'px';
		selection.style.width = width + 'px';
		selection.style.height = height + 'px';

		if (cropCopy) {
			cropCopy.disabled = disabled;
		}

		if (cropActions) {
			cropActions.hidden = disabled;

			if (!disabled) {
				const actionsTop = top >= 48 ? top - 42 : top + height + 8;
				cropActions.style.left = left + 'px';
				cropActions.style.top = actionsTop + 'px';
			}
		}
	}

	function getSelectedCanvasRect() {
		if (!state.cropStart || !state.cropEnd) {
			return null;
		}

		const left = Math.min(state.cropStart.canvasX, state.cropEnd.canvasX);
		const top = Math.min(state.cropStart.canvasY, state.cropEnd.canvasY);
		const width = Math.abs(state.cropStart.canvasX - state.cropEnd.canvasX);
		const height = Math.abs(state.cropStart.canvasY - state.cropEnd.canvasY);

		if (width < 5 || height < 5) {
			return null;
		}

		return { left: left, top: top, width: width, height: height };
	}

	async function copyCurrentPageToClipboard() {
		const modal = document.getElementById('cvpr-modal');
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;

		if (!canvas || !state.canCrop) {
			return;
		}

		await copyCanvasAreaToClipboard(canvas, 0, 0, canvas.width, canvas.height, slugify(state.title) + '-pagina-' + state.page + '.png');
	}

	async function copySelectedAreaToClipboard() {
		const modal = document.getElementById('cvpr-modal');
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;
		const rect = getSelectedCanvasRect();

		if (!canvas || !rect || !state.canCrop) {
			return;
		}

		await copyCanvasAreaToClipboard(canvas, rect.left, rect.top, rect.width, rect.height, slugify(state.title) + '-recorte-pagina-' + state.page + '.png');
	}


	
	async function saveCurrentPageAsPng() {
		const modal = document.getElementById('cvpr-modal');
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;

		if (!canvas || !state.canCrop) {
			return;
		}

		const outputCanvas = createAreaCanvas(canvas, 0, 0, canvas.width, canvas.height);
		const blob = await canvasToBlob(outputCanvas);

		if (blob) {
			downloadBlob(blob, slugify(state.title) + '-pagina-' + state.page + '.png');
		}
	}

	async function shareCurrentPageWithSystem() {
		const modal = document.getElementById('cvpr-modal');
		const resultsBox = modal ? modal.querySelector('.cvpr-search-results') : null;
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;

		if (!canvas || !state.canCrop) {
			return;
		}

		const filename = slugify(state.title) + '-pagina-' + state.page + '.png';
		const outputCanvas = createAreaCanvas(canvas, 0, 0, canvas.width, canvas.height);
		const blob = await canvasToBlob(outputCanvas);

		if (!blob) {
			if (resultsBox) {
				resultsBox.textContent = 'Não foi possível criar a imagem para partilha.';
			}
			return;
		}

		const file = new File([blob], filename, { type: 'image/png' });
		const shareText = 'Página do catálogo: ' + (state.title || 'Catálogo') + ' - Página ' + state.page;

		try {
			if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
				await navigator.share({
					title: 'Página de catálogo - Chave Vertical',
					text: shareText,
					files: [file]
				});

				if (resultsBox) {
					resultsBox.textContent = 'Partilha aberta pelo sistema operativo.';
				}

				return;
			}

			throw new Error('Partilha de ficheiros indisponível.');
		} catch (e) {
			if (e && e.name === 'AbortError') {
				return;
			}

			downloadBlob(blob, filename);

			if (resultsBox) {
				resultsBox.textContent = 'Este browser não permitiu abrir a partilha do sistema. A página foi guardada em PNG.';
			}
		}
	}

	async function saveSelectedAreaAsPng() {
		const modal = document.getElementById('cvpr-modal');
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;
		const rect = getSelectedCanvasRect();

		if (!canvas || !rect || !state.canCrop) {
			return;
		}

		const outputCanvas = createAreaCanvas(canvas, rect.left, rect.top, rect.width, rect.height);
		const blob = await canvasToBlob(outputCanvas);

		if (blob) {
			downloadBlob(blob, slugify(state.title) + '-recorte-pagina-' + state.page + '.png');
		}
	}

	async function shareSelectedAreaWithSystem() {
		const modal = document.getElementById('cvpr-modal');
		const resultsBox = modal ? modal.querySelector('.cvpr-search-results') : null;
		const canvas = modal ? modal.querySelector('.cvpr-canvas') : null;
		const rect = getSelectedCanvasRect();

		if (!canvas || !rect || !state.canCrop) {
			return;
		}

		const filename = slugify(state.title) + '-recorte-pagina-' + state.page + '.png';
		const outputCanvas = createAreaCanvas(canvas, rect.left, rect.top, rect.width, rect.height);
		const blob = await canvasToBlob(outputCanvas);

		if (!blob) {
			if (resultsBox) {
				resultsBox.textContent = 'Não foi possível criar a imagem para partilha.';
			}
			return;
		}

		const file = new File([blob], filename, { type: 'image/png' });
		const shareText = 'Recorte do catálogo: ' + (state.title || 'Catálogo') + ' - Página ' + state.page;

		try {
			if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
				await navigator.share({
					title: 'Recorte de catálogo - Chave Vertical',
					text: shareText,
					files: [file]
				});

				if (resultsBox) {
					resultsBox.textContent = 'Partilha aberta pelo sistema operativo.';
				}

				return;
			}

			throw new Error('Partilha de ficheiros indisponível.');
		} catch (e) {
			if (e && e.name === 'AbortError') {
				return;
			}

			downloadBlob(blob, filename);

			if (resultsBox) {
				resultsBox.textContent = 'Este browser não permitiu abrir a partilha do sistema. O recorte foi guardado em PNG.';
			}
		}
	}

	async function copyCanvasAreaToClipboard(sourceCanvas, sx, sy, sw, sh, filename) {
		const modal = document.getElementById('cvpr-modal');
		const resultsBox = modal ? modal.querySelector('.cvpr-search-results') : null;
		const outputCanvas = createAreaCanvas(sourceCanvas, sx, sy, sw, sh);
		const blob = await canvasToBlob(outputCanvas);

		if (!blob) {
			if (resultsBox) {
				resultsBox.textContent = 'Não foi possível criar a imagem.';
			}
			return;
		}

		try {
			if (navigator.clipboard && window.ClipboardItem && window.isSecureContext) {
				await navigator.clipboard.write([
					new ClipboardItem({
						'image/png': blob
					})
				]);

				if (resultsBox) {
					resultsBox.textContent = 'Imagem copiada para o clipboard.';
				}

				return;
			}

			throw new Error('Clipboard indisponível.');
		} catch (e) {
			downloadBlob(blob, filename);

			if (resultsBox) {
				resultsBox.textContent = 'O browser não permitiu copiar para o clipboard. A imagem foi descarregada.';
			}
		}
	}

	function createAreaCanvas(sourceCanvas, sx, sy, sw, sh) {
		const outputCanvas = document.createElement('canvas');
		outputCanvas.width = Math.max(1, Math.round(sw));
		outputCanvas.height = Math.max(1, Math.round(sh));

		const context = outputCanvas.getContext('2d');

		context.drawImage(sourceCanvas, sx, sy, sw, sh, 0, 0, outputCanvas.width, outputCanvas.height);

		return outputCanvas;
	}

	function canvasToBlob(canvas) {
		return new Promise(function (resolve) {
			canvas.toBlob(resolve, 'image/png');
		});
	}

	function downloadBlob(blob, filename) {
		const objectUrl = URL.createObjectURL(blob);
		const link = document.createElement('a');

		link.href = objectUrl;
		link.download = filename || 'ficheiro.png';

		document.body.appendChild(link);
		link.click();
		link.remove();

		window.setTimeout(function () {
			URL.revokeObjectURL(objectUrl);
		}, 1000);
	}

	function promptPrintRange() {
		if (!state.canPrint) {
			return;
		}

		const value = window.prompt('Indique as páginas a imprimir. Exemplo: 1,3,5-8', String(state.page));

		if (!value) {
			return;
		}

		const pages = parsePageRange(value, state.total);

		if (!pages.length) {
			window.alert('Intervalo de páginas inválido.');
			return;
		}

		printPages(pages);
	}

	function parsePageRange(value, totalPages) {
		const pages = [];
		const parts = String(value || '').split(',');

		parts.forEach(function (part) {
			part = part.trim();

			if (!part) {
				return;
			}

			if (part.indexOf('-') !== -1) {
				const rangeParts = part.split('-');
				const start = parseInt(rangeParts[0], 10);
				const end = parseInt(rangeParts[1], 10);

				if (!start || !end || start > end) {
					return;
				}

				for (let i = start; i <= end; i++) {
					if (i >= 1 && i <= totalPages && pages.indexOf(i) === -1) {
						pages.push(i);
					}
				}
			} else {
				const page = parseInt(part, 10);

				if (page >= 1 && page <= totalPages && pages.indexOf(page) === -1) {
					pages.push(page);
				}
			}
		});

		return pages.sort(function (a, b) {
			return a - b;
		});
	}

	async function renderExternalPageToCanvas(url, pageNumber, targetWidth) {
		try {
			const pdf = await window.pdfjsLib.getDocument({ url: url, withCredentials: false }).promise;
			const page = await pdf.getPage(pageNumber);
			const baseViewport = page.getViewport({ scale: 1 });
			const viewport = page.getViewport({ scale: targetWidth / baseViewport.width });
			const canvas = document.createElement('canvas');

			canvas.width = viewport.width;
			canvas.height = viewport.height;

			await page.render({
				canvasContext: canvas.getContext('2d'),
				viewport: viewport
			}).promise;

			return canvas;
		} catch (e) {
			return null;
		}
	}

	async function printPages(pageNumbers) {
		const modal = document.getElementById('cvpr-modal');
		const resultsBox = modal ? modal.querySelector('.cvpr-search-results') : null;

		if (!state.pdf || !state.canPrint || !pageNumbers.length) {
			if (resultsBox) {
				resultsBox.textContent = 'A impressão não está disponível para este utilizador.';
			}
			return;
		}

		if (pageNumbers.length > 50 && !window.confirm('Vai imprimir ' + pageNumbers.length + ' páginas. Deseja continuar?')) {
			return;
		}

		const printWindow = openPrintWindowShell();

		if (resultsBox) {
			resultsBox.textContent = 'A preparar impressão...';
		}

		const images = [];

		try {
			for (let i = 0; i < pageNumbers.length; i++) {
				const pageNumber = pageNumbers[i];

				if (resultsBox) {
					resultsBox.textContent = 'A preparar impressão... página ' + pageNumber + ' (' + (i + 1) + '/' + pageNumbers.length + ')';
				}

				if (printWindow && !printWindow.closed) {
					printWindow.document.body.innerHTML = '<p style="font-family:Arial,sans-serif;padding:20px;">A preparar impressão... página ' + pageNumber + ' (' + (i + 1) + '/' + pageNumbers.length + ')</p>';
				}

				const page = await state.pdf.getPage(pageNumber);
				const baseViewport = page.getViewport({ scale: 1 });
				const viewport = page.getViewport({ scale: 1400 / baseViewport.width });
				const canvas = document.createElement('canvas');

				canvas.width = viewport.width;
				canvas.height = viewport.height;

				await page.render({
					canvasContext: canvas.getContext('2d'),
					viewport: viewport
				}).promise;

				images.push({
					page: pageNumber,
					src: canvas.toDataURL('image/png')
				});
			}

			openPrintFrame(images, printWindow);

			if (resultsBox) {
				resultsBox.textContent = 'Janela de impressão preparada.';
			}
		} catch (e) {
			if (printWindow && !printWindow.closed) {
				printWindow.close();
			}

			if (resultsBox) {
				resultsBox.textContent = 'Não foi possível preparar a impressão.';
			}
		}
	}

	function openPrintWindowShell() {
		let printWindow = null;

		try {
			printWindow = window.open('', '_blank');
		} catch (e) {
			printWindow = null;
		}

		if (printWindow) {
			printWindow.document.open();
			printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>A preparar impressão...</title></head><body><p style="font-family:Arial,sans-serif;padding:20px;">A preparar impressão...</p></body></html>');
			printWindow.document.close();
		}

		return printWindow;
	}

	function openPrintFrame(images, printWindow) {
		const useWindow = printWindow && !printWindow.closed;
		let doc;
		let iframe = null;

		if (useWindow) {
			doc = printWindow.document;
		} else {
			iframe = document.createElement('iframe');
			iframe.style.position = 'fixed';
			iframe.style.right = '0';
			iframe.style.bottom = '0';
			iframe.style.width = '1px';
			iframe.style.height = '1px';
			iframe.style.opacity = '0';
			iframe.style.border = '0';
			document.body.appendChild(iframe);
			doc = iframe.contentWindow.document;
		}

		let html = '';

		html += '<!doctype html><html><head><meta charset="utf-8">';
		html += '<title>Imprimir PDF - Chave Vertical</title>';
		html += '<style>';
		html += '@page { margin: 10mm; }';
		html += 'html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, sans-serif; }';
		html += '.print-page { page-break-after: always; break-after: page; text-align: center; }';
		html += '.print-page:last-child { page-break-after: auto; break-after: auto; }';
		html += '.print-header { margin: 0 0 8mm 0; padding: 0 0 4mm 0; border-bottom: 1px solid #ddd; color: #111; text-align: center; }';
		html += '.print-header-title { display: block; margin-bottom: 3mm; font-size: 14px; font-weight: 800; letter-spacing: 0.4px; text-transform: uppercase; }';
		html += '.print-header-contacts { display: flex; align-items: center; justify-content: center; gap: 14px; font-size: 12px; font-weight: 700; letter-spacing: 0.3px; text-transform: uppercase; flex-wrap: wrap; }';
		html += '.print-header-contacts span { white-space: nowrap; }';
		html += '.print-header-contacts .print-email { text-transform: lowercase; font-weight: 600; }';
		html += '.print-page-number { margin-top: 4mm; font-size: 10px; color: #666; }';
		html += 'img { max-width: 100%; height: auto; display: block; margin: 0 auto; }';
		html += '</style></head><body>';

		images.forEach(function (image) {
			html += '<div class="print-page">';
			html += '<div class="print-header">';
			html += '<span class="print-header-title">Caso pretenda receber um orçamento, contacte-nos</span>';
			html += '<div class="print-header-contacts">';
			html += '<span>CHAVEVERTICAL.COM</span>';
			html += '<span class="print-email">info@chavevertical.com</span>';
			html += '<span>914 580 410</span>';
			html += '</div></div>';
			html += '<img src="' + image.src + '" alt="Página ' + image.page + '">';
			html += '<div class="print-page-number">Página ' + image.page + '</div>';
			html += '</div>';
		});

		html += '</body></html>';

		doc.open();
		doc.write(html);
		doc.close();

		const targetWindow = useWindow ? printWindow : iframe.contentWindow;

		window.setTimeout(function () {
			targetWindow.focus();
			targetWindow.print();

			if (iframe) {
				window.setTimeout(function () {
					iframe.remove();
				}, 1500);
			}
		}, 650);
	}

	function getCatalogShareUrl() {
		const pageUrl = new URL(window.location.href);

		if (state.id) {
			pageUrl.searchParams.set('cv_pdf', state.id);
		}

		return pageUrl.toString();
	}

	function getInternationalPhoneNumber(number) {
		const digits = String(number || '').replace(/\D+/g, '');

		if (!digits) {
			return '351914580410';
		}

		if (digits.length === 9 && digits.charAt(0) === '9') {
			return '351' + digits;
		}

		return digits;
	}

	function setContactLinks() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal) {
			return;
		}

		const site = modal.querySelector('.cvpr-contact-site');
		const email = modal.querySelector('.cvpr-contact-email');
		const phone = modal.querySelector('.cvpr-contact-phone');
		const whatsapp = modal.querySelector('.cvpr-contact-whatsapp');
		const telegram = modal.querySelector('.cvpr-contact-telegram');
		const internationalPhone = getInternationalPhoneNumber(state.phoneDigits);
		const whatsappNumber = getInternationalPhoneNumber(state.whatsappNumber);
		const shortMessage = 'Olá, pretendo receber uma cotação através do catálogo ' + (state.title || 'Chave Vertical') + '.';

		if (site) {
			site.href = state.websiteUrl || 'https://chavevertical.com';
			site.innerHTML = '🌐 CHAVEVERTICAL.COM';
		}

		if (email) {
			email.href = 'mailto:' + encodeURIComponent(state.quoteEmail || 'info@chavevertical.com');
			email.innerHTML = '✉️ ' + esc(state.quoteEmail || 'info@chavevertical.com');
		}

		if (phone) {
			phone.href = 'tel:+' + internationalPhone;
			phone.innerHTML = '📞 ' + esc(state.phoneDisplay || '914 580 410');
		}

		if (whatsapp) {
			whatsapp.href = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(shortMessage);
		}

		if (telegram) {
			if (state.telegramUrl) {
				telegram.href = state.telegramUrl;
			} else {
				telegram.href = 'https://t.me/share/url?url=' + encodeURIComponent(getCatalogShareUrl()) + '&text=' + encodeURIComponent(shortMessage);
			}
		}
	}

	function openQuotePanel() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !state.canQuote) {
			return;
		}

		const panel = modal.querySelector('.cvpr-quote-panel');
		const meta = modal.querySelector('.cvpr-quote-meta');
		const textArea = modal.querySelector('.cvpr-quote-text');
		const selectedText = getSelectedText();

		meta.innerHTML =
			'<strong>Catálogo:</strong> ' + esc(state.title) +
			' &nbsp; <strong>Página:</strong> ' + esc(state.page);

		if (selectedText) {
			textArea.value = selectedText;
		}

		panel.hidden = false;
		textArea.focus();
	}

	function closeQuotePanel() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal) {
			return;
		}

		modal.querySelector('.cvpr-quote-panel').hidden = true;
	}

	function prepareQuoteFromSelection() {
		const modal = document.getElementById('cvpr-modal');

		if (!modal || !state.canQuote) {
			return;
		}

		const selectedText = getSelectedText();
		const textArea = modal.querySelector('.cvpr-quote-text');

		if (selectedText && textArea) {
			textArea.value = selectedText;
		}
	}

	function getQuoteMessage() {
		const modal = document.getElementById('cvpr-modal');
		const productText = modal ? (modal.querySelector('.cvpr-quote-text').value.trim() || getSelectedText()) : getSelectedText();
		const notes = modal ? modal.querySelector('.cvpr-quote-notes').value.trim() : '';
		const pageUrl = getCatalogShareUrl();

		return [
			'Olá, pretendo receber uma cotação para o seguinte produto:',
			'',
			'Catálogo: ' + (state.title || 'Catálogo'),
			'Página: ' + state.page,
			'',
			'Texto selecionado / produto:',
			productText || '(não indicado)',
			'',
			'Observações:',
			notes || '(sem observações)',
			'',
			'Link do catálogo:',
			pageUrl,
			'',
			'Obrigado.'
		].join('\n');
	}

	function sendQuoteByWhatsapp() {
		const number = getInternationalPhoneNumber(state.whatsappNumber || '351914580410');
		const url = 'https://wa.me/' + number + '?text=' + encodeURIComponent(getQuoteMessage());

		window.open(url, '_blank', 'noopener,noreferrer');
	}

	function sendQuoteByEmail() {
		const subject = 'Pedido de cotação - ' + (state.title || 'Catálogo') + ' - Página ' + state.page;
		const url =
			'mailto:' +
			encodeURIComponent(state.quoteEmail || 'info@chavevertical.com') +
			'?subject=' +
			encodeURIComponent(subject) +
			'&body=' +
			encodeURIComponent(getQuoteMessage());

		window.location.href = url;
	}

	function sendQuoteByPhone() {
		const number = getInternationalPhoneNumber(state.phoneDigits || state.whatsappNumber || '914580410');

		window.location.href = 'tel:+' + number;
	}

	function updateUrlParams(pdfId, query) {
		if (!window.history || !pdfId) {
			return;
		}

		const url = new URL(window.location.href);
		url.searchParams.set('cv_pdf', pdfId);

		if (query) {
			url.searchParams.set('q', query);
		} else {
			url.searchParams.delete('q');
		}

		window.history.replaceState({}, '', url.toString());
	}

	function initCards() {
		const cards = document.querySelectorAll('.cvpr-card');

		cards.forEach(function (card) {
			const openButton = card.querySelector('.cvpr-card__open');

			if (openButton) {
				openButton.addEventListener('click', function () {
					updateUrlParams(card.dataset.cvPdfId, '');
					openReader(card, '');
				});
			}
		});

		if ('IntersectionObserver' in window) {
			const observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						renderCover(entry.target);
						observer.unobserve(entry.target);
					}
				});
			}, {
				rootMargin: '200px 0px'
			});

			cards.forEach(function (card) {
				observer.observe(card);
			});
		} else {
			cards.forEach(function (card) {
				renderCover(card);
			});
		}
	}

	function initDeepLink() {
		const params = new URLSearchParams(window.location.search);
		const requestedPdf = params.get('cv_pdf');
		const requestedSearch = params.get('q') || '';

		if (!requestedPdf) {
			return;
		}

		const card = findCard(requestedPdf);

		if (card) {
			renderCover(card);
			openReader(card, requestedSearch);
		}
	}

	function initResizeHandler() {
		window.addEventListener('resize', function () {
			const modal = document.getElementById('cvpr-modal');

			if (!modal || !modal.classList.contains('is-open')) {
				return;
			}

			if (state.zoomMode !== 'fit-page' && state.zoomMode !== 'fit-width') {
				return;
			}

			window.clearTimeout(resizeTimer);

			resizeTimer = window.setTimeout(function () {
				if (state.pdf && state.page) {
					renderPage(state.page);
				}
			}, 200);
		});
	}

	ready(function () {
		if (!window.pdfjsLib) {
			return;
		}

		setWorker();
		createModal();
		initCards();
		initDeepLink();
		initResizeHandler();
	});
})();
JS;

	$js_config = 'window.CVPR_CONFIG = ' . wp_json_encode(
		array(
			'workerSrc'     => $worker_src,
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'cvpr2_save_pdf_cover' ),
			'canSaveCovers' => current_user_can( 'manage_options' ),
		)
	) . ';';

	wp_add_inline_script( 'cvpr2-pdfjs', $js_config . "\n" . $js, 'after' );

	wp_register_style(
		'cvpr2-snippet-style',
		false,
		array(),
		CVPR2_SNIPPET_VERSION
	);

	wp_enqueue_style( 'cvpr2-snippet-style' );

	$css = <<<'CSS'
.cvpr-lock-scroll {
	overflow: hidden;
}

.cvpr-card [hidden],
.cvpr-modal [hidden] {
	display: none !important;
}

.cvpr-card__preview--has-cover .cvpr-card__loading {
	display: none !important;
}

.cvpr-card {
	width: 100%;
	max-width: var(--cvpr-card-width, 360px);
	margin: 20px 0;
}

.cvpr-card__open {
	display: block;
	width: 100%;
	padding: 0;
	border: 0;
	background: transparent;
	text-align: left;
	cursor: pointer;
}

.cvpr-card__preview {
	position: relative;
	display: block;
	width: 100%;
	min-height: 260px;
	background: #f5f5f5;
	border: 1px solid #ddd;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.cvpr-card__canvas {
	display: none;
	width: 100%;
	height: auto;
}

.cvpr-card__cover-img {
	display: block;
	width: 100%;
	height: auto;
}

.cvpr-card__loading,
.cvpr-card__error {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
	text-align: center;
	font-size: 14px;
	color: #555;
}

.cvpr-card__error {
	display: none;
}

.cvpr-card__title {
	display: block;
	margin-top: 10px;
	font-weight: 600;
	font-size: 16px;
	color: #222;
	user-select: text;
	-webkit-user-select: text;
}

.cvpr-card__open:hover .cvpr-card__preview {
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16);
}

.cvpr-card__download-image {
	margin-top: 8px;
	padding: 7px 12px;
	border: 1px solid #aaa;
	border-radius: 4px;
	background: #fff;
	color: #222;
	font-size: 13px;
	cursor: pointer;
}

.cvpr-card__download-image:hover {
	background: #eee;
}

.cvpr-modal {
	position: fixed;
	z-index: 999999;
	inset: 0;
	display: none;
	background: rgba(0, 0, 0, 0.72);
}

.cvpr-modal.is-open {
	display: block;
}

.cvpr-modal__dialog {
	position: absolute;
	inset: 12px;
	height: calc(100dvh - 24px);
	max-height: calc(100dvh - 24px);
	display: flex;
	flex-direction: column;
	overflow: hidden;
	background: #fff;
	border-radius: 10px;
}

.cvpr-contactbar {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 10px 12px;
	background: linear-gradient(90deg, #111 0%, #222 100%);
	color: #fff;
	flex-wrap: wrap;
}

.cvpr-contactbar__info,
.cvpr-contactbar__buttons {
	display: flex;
	gap: 8px;
	align-items: center;
	flex-wrap: wrap;
}

.cvpr-contact-link,
.cvpr-contact-button,
.cvpr-quote-open {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	min-height: 34px;
	padding: 7px 11px;
	border-radius: 999px;
	font-size: 13px;
	font-weight: 700;
	line-height: 1.1;
	text-decoration: none;
	white-space: nowrap;
	box-sizing: border-box;
}

.cvpr-contact-link {
	border: 1px solid rgba(255, 255, 255, 0.28);
	background: rgba(255, 255, 255, 0.1);
	color: #fff;
}

.cvpr-contact-link:hover {
	background: rgba(255, 255, 255, 0.2);
	color: #fff;
	text-decoration: none;
}

.cvpr-contact-email {
	background: rgba(255, 255, 255, 0.16);
}

.cvpr-contact-phone {
	background: rgba(255, 255, 255, 0.22);
}

.cvpr-contact-button {
	border: 0;
}

.cvpr-contact-whatsapp {
	background: #25d366;
	color: #063d1e;
}

.cvpr-contact-whatsapp:hover {
	background: #1ebe5d;
	color: #063d1e;
	text-decoration: none;
}

.cvpr-contact-telegram {
	background: #229ed9;
	color: #fff;
}

.cvpr-contact-telegram:hover {
	background: #168cc4;
	color: #fff;
	text-decoration: none;
}

.cvpr-quote-open {
	border: 1px solid #fff;
	background: #fff;
	color: #111;
	cursor: pointer;
}

.cvpr-quote-open:hover {
	background: #f0f0f0;
}

.cvpr-toolbar {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	padding: 10px 12px;
	border-bottom: 1px solid #ddd;
	background: #f8f8f8;
}

.cvpr-toolbar__title {
	font-size: 16px;
	font-weight: 700;
	color: #222;
	user-select: text;
	-webkit-user-select: text;
}

.cvpr-search {
	display: flex;
	gap: 6px;
	align-items: center;
}

.cvpr-search__input {
	min-width: 240px;
	padding: 7px 9px;
	border: 1px solid #bbb;
	border-radius: 4px;
	font-size: 14px;
}

.cvpr-actions {
	display: flex;
	gap: 6px;
	align-items: center;
	flex-wrap: wrap;
}

.cvpr-toolbar button,
.cvpr-download-pdf,
.cvpr-result,
.cvpr-quote-actions button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 7px 10px;
	border: 1px solid #aaa;
	border-radius: 4px;
	background: #fff;
	color: #222;
	text-decoration: none;
	cursor: pointer;
	font-size: 13px;
	line-height: 1.2;
}

.cvpr-toolbar button:hover,
.cvpr-download-pdf:hover,
.cvpr-result:hover,
.cvpr-quote-actions button:hover {
	background: #eee;
	color: #222;
	text-decoration: none;
}

.cvpr-page-info,
.cvpr-zoom-info {
	font-size: 13px;
	color: #333;
	white-space: nowrap;
}

.cvpr-search-results {
	padding: 8px 12px;
	border-bottom: 1px solid #eee;
	font-size: 14px;
	color: #222;
}

.cvpr-result {
	margin: 3px;
}

.cvpr-canvas-wrap {
	flex: 1;
	min-height: 0;
	overflow: auto;
	padding: 20px;
	text-align: center;
	background: #777;
}

.cvpr-canvas-stage {
	position: relative;
	display: inline-block;
	line-height: 0;
}

.cvpr-canvas-stage.is-cropping .cvpr-canvas {
	cursor: crosshair;
}

.cvpr-canvas {
	display: block;
	background: #fff;
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
}

.cvpr-text-layer {
	position: absolute;
	inset: 0;
	overflow: hidden;
	opacity: 1;
	line-height: 1;
	text-align: initial;
	pointer-events: auto;
	user-select: text;
	-webkit-user-select: text;
	transform-origin: 0 0;
	z-index: 1;
}

.cvpr-text-layer span,
.cvpr-text-layer br {
	position: absolute;
	color: transparent;
	white-space: pre;
	cursor: text;
	transform-origin: 0% 0%;
	user-select: text;
	-webkit-user-select: text;
}

.cvpr-text-layer ::selection {
	background: rgba(0, 120, 215, 0.35);
}

.cvpr-canvas-stage.is-cropping .cvpr-text-layer {
	pointer-events: none;
	user-select: none;
	-webkit-user-select: none;
}

.cvpr-crop-selection {
	position: absolute;
	z-index: 2;
	border: 2px dashed #fff;
	background: rgba(0, 115, 170, 0.22);
	box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.12);
	pointer-events: none;
	box-sizing: border-box;
}



.cvpr-context-menu {
	position: fixed;
	z-index: 1000002;
	min-width: 220px;
	padding: 6px;
	border-radius: 8px;
	background: #111;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
	color: #fff;
}

.cvpr-context-menu[hidden] {
	display: none;
}

.cvpr-context-menu button {
	display: block;
	width: 100%;
	padding: 8px 10px;
	border: 0;
	border-radius: 5px;
	background: transparent;
	color: #fff;
	text-align: left;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
}

.cvpr-context-menu button:hover {
	background: rgba(255, 255, 255, 0.14);
}

.cvpr-context-menu button[hidden] {
	display: none;
}

.cvpr-context-menu hr {
	margin: 6px 0;
	border: 0;
	border-top: 1px solid rgba(255, 255, 255, 0.18);
}

.cvpr-crop-actions {
	position: absolute;
	z-index: 4;
	display: flex;
	gap: 6px;	
	align-items: center;
	padding: 6px;
	border-radius: 8px;
	background: rgba(17, 17, 17, 0.92);
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.28);
	pointer-events: auto;
}

.cvpr-crop-actions[hidden] {
	display: none;
}

.cvpr-crop-actions button {
	padding: 6px 9px;
	border: 1px solid #fff;
	border-radius: 5px;
	background: #fff;
	color: #111;
	font-size: 12px;
	font-weight: 700;
	cursor: pointer;
	line-height: 1.2;
	white-space: nowrap;
}

.cvpr-crop-actions button:hover {
	background: #e9e9e9;
}

.cvpr-crop-copy:disabled {
	opacity: 0.45;
	cursor: not-allowed;
}

.cvpr-quote-panel {
	position: absolute;
	inset: 0;
	z-index: 3;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(0, 0, 0, 0.45);
	padding: 20px;
}

.cvpr-quote-panel[hidden] {
	display: none;
}

.cvpr-quote-panel__inner {
	width: min(620px, 100%);
	background: #fff;
	border-radius: 10px;
	box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
	padding: 18px;
	color: #222;
}

.cvpr-quote-panel__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 10px;
}

.cvpr-quote-panel__head strong {
	font-size: 18px;
}

.cvpr-quote-close {
	border: 0;
	background: #eee;
	border-radius: 50%;
	width: 32px;
	height: 32px;
	cursor: pointer;
	font-size: 20px;
	line-height: 1;
}

.cvpr-quote-help {
	margin: 0 0 12px;
	font-size: 14px;
	color: #444;
}

.cvpr-quote-meta {
	margin-bottom: 12px;
	padding: 8px 10px;
	background: #f4f4f4;
	border-radius: 5px;
	font-size: 13px;
}

.cvpr-quote-panel label {
	display: block;
	margin: 10px 0 5px;
	font-weight: 700;
	font-size: 13px;
}

.cvpr-quote-panel textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid #bbb;
	border-radius: 5px;
	padding: 8px;
	font-size: 14px;
	resize: vertical;
}

.cvpr-quote-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: flex-end;
	margin-top: 12px;
}

.cvpr-quote-whatsapp {
	border-color: #25d366 !important;
	color: #075e54 !important;
	font-weight: 700;
}

@media (max-width: 768px) {
	.cvpr-modal__dialog {
		inset: 5px;
		height: calc(100dvh - 10px);
		max-height: calc(100dvh - 10px);
		border-radius: 0;
	}

	.cvpr-contactbar,
	.cvpr-toolbar {
		align-items: stretch;
	}

	.cvpr-contactbar__info,
	.cvpr-contactbar__buttons,
	.cvpr-toolbar__title,
	.cvpr-search,
	.cvpr-actions {
		width: 100%;
	}

	.cvpr-search__input {
		min-width: 0;
		flex: 1;
	}

	.cvpr-actions {
		gap: 5px;
	}

	.cvpr-toolbar button,
	.cvpr-download-pdf,
	.cvpr-result {
		padding: 7px 8px;
		font-size: 12px;
	}

	.cvpr-canvas-wrap {
		padding: 10px;
	}
}
CSS;

	wp_add_inline_style( 'cvpr2-snippet-style', $css );
}


/**
 * CV PDF Catalogos Automatico
 *
 * Le automaticamente todos os PDFs existentes em:
 * /wp-content/uploads/catalogos/
 *
 * Requer que este snippet principal ja tenha a funcao:
 * cvpr2_pdf_card_shortcode()
 *
 * Shortcode principal:
 * [cv_pdf_catalogos_auto]
 *
 * Opcoes:
 * [cv_pdf_catalogos_auto colunas="4"]
 * [cv_pdf_catalogos_auto pesquisa="nao"]
 * [cv_pdf_catalogos_auto recursivo="sim"]
 */

if ( ! shortcode_exists( 'cv_pdf_catalogos_auto' ) ) {
	add_shortcode( 'cv_pdf_catalogos_auto', 'cvpr2_pdf_catalogos_auto_shortcode' );
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_shortcode' ) ) {
	function cvpr2_pdf_catalogos_auto_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'pasta'           => 'catalogos',
				'titulo'          => 'Catálogos',
				'subtitulo'       => 'Consulte os catálogos disponíveis.',
				'colunas'         => '6',
				'pesquisa'        => 'sim',
				'recursivo'       => 'nao',
				'pdf_download'    => 'admin',
				'crop'            => 'admin',
				'print'           => 'admin',
				'quote'           => 'all',
				'whatsapp_number' => '351914580410',
				'quote_email'     => 'info@chavevertical.com',
				'phone'           => '914 580 410',
				'website_url'     => 'https://chavevertical.com',
				'telegram_url'    => '',
			),
			$atts,
			'cv_pdf_catalogos_auto'
		);

		if ( ! function_exists( 'cvpr2_pdf_card_shortcode' ) ) {
			return '<p>O leitor PDF principal não está disponível. Confirma se o snippet do shortcode <code>[cv_pdf_card]</code> está ativo.</p>';
		}

		$items = cvpr2_pdf_catalogos_auto_get_pdfs( $atts['pasta'], $atts['recursivo'] );

		if ( empty( $items ) ) {
			return '<p>Não foram encontrados PDFs na pasta <code>/wp-content/uploads/catalogos/</code>.</p>';
		}

		usort(
			$items,
			function ( $a, $b ) {
				return strcasecmp(
					remove_accents( $a['nome'] ),
					remove_accents( $b['nome'] )
				);
			}
		);

		$iniciais = array();

		foreach ( $items as $index => $item ) {
			$initial = cvpr2_pdf_catalogos_auto_initial( $item['nome'] );

			$items[ $index ]['initial'] = $initial;
			$iniciais[ $initial ]       = $initial;
		}

		ksort( $iniciais, SORT_NATURAL | SORT_FLAG_CASE );

		$uid               = wp_unique_id( 'cvpr-auto-catalogos-' );
		$colunas           = max( 1, min( 6, absint( $atts['colunas'] ) ) );
		$mostrar_pesquisa  = 'nao' !== strtolower( sanitize_text_field( $atts['pesquisa'] ) );
		$total_catalogos   = count( $items );
		$titulo_principal  = cvpr2_pdf_catalogos_auto_mb_upper( sanitize_text_field( $atts['titulo'] ) );

		ob_start();
		?>

		<div id="<?php echo esc_attr( $uid ); ?>" class="cvpr-auto-catalogos" style="--cvpr-auto-cols: <?php echo esc_attr( $colunas ); ?>;">

			<div class="cvpr-auto-catalogos-hero">
				<div class="cvpr-auto-catalogos-title-wrap">
					<span class="cvpr-auto-catalogos-kicker">CHAVE VERTICAL</span>

					<?php if ( ! empty( $titulo_principal ) ) : ?>
						<h2><?php echo esc_html( $titulo_principal ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $atts['subtitulo'] ) ) : ?>
						<p><?php echo esc_html( $atts['subtitulo'] ); ?></p>
					<?php endif; ?>

					<span class="cvpr-auto-catalogos-count">
						<?php echo esc_html( $total_catalogos ); ?> catálogo<?php echo 1 === $total_catalogos ? '' : 's'; ?> disponível<?php echo 1 === $total_catalogos ? '' : 'is'; ?>
					</span>
				</div>
			</div>

			<div class="cvpr-auto-catalogos-toolbar">
				<?php if ( $mostrar_pesquisa ) : ?>
					<div class="cvpr-auto-catalogos-search-wrap">
						<span class="cvpr-auto-catalogos-search-icon">🔎</span>
						<input
							type="search"
							class="cvpr-auto-catalogos-search"
							placeholder="Pesquisar catálogo..."
							aria-label="Pesquisar catálogo"
						>
					</div>
				<?php endif; ?>

				<div class="cvpr-auto-catalogos-letters" aria-label="Filtrar por letra inicial">
					<button type="button" class="cvpr-auto-catalogos-letter is-active" data-letter="todos">
						Todos
					</button>

					<?php foreach ( $iniciais as $inicial ) : ?>
						<button type="button" class="cvpr-auto-catalogos-letter" data-letter="<?php echo esc_attr( $inicial ); ?>">
							<?php echo esc_html( $inicial ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="cvpr-auto-catalogos-grid">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$search_text = strtolower(
						remove_accents(
							$item['nome'] . ' ' . $item['titulo'] . ' ' . $item['ficheiro']
						)
					);
					?>

					<div
						class="cvpr-auto-catalogos-item"
						data-search="<?php echo esc_attr( $search_text ); ?>"
						data-initial="<?php echo esc_attr( $item['initial'] ); ?>"
					>
						<?php
						echo cvpr2_pdf_card_shortcode(
							array(
								'id'              => $item['id'],
								'url'             => $item['url'],
								'title'           => $item['titulo'],
								'width'           => '100%',
								'pdf_download'    => $atts['pdf_download'],
								'crop'            => $atts['crop'],
								'print'           => $atts['print'],
								'quote'           => $atts['quote'],
								'whatsapp_number' => $atts['whatsapp_number'],
								'quote_email'     => $atts['quote_email'],
								'phone'           => $atts['phone'],
								'website_url'     => $atts['website_url'],
								'telegram_url'    => $atts['telegram_url'],
							)
						);
						?>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="cvpr-auto-catalogos-empty" hidden>
				Não foram encontrados catálogos para a pesquisa ou filtro selecionado.
			</p>

		</div>

		<style>
			#<?php echo esc_attr( $uid ); ?>.cvpr-auto-catalogos {
				width: 100%;
				--cvpr-dark: #181818;
				--cvpr-muted: #666;
				--cvpr-border: #e6e6e6;
				--cvpr-soft: #f7f7f7;
				--cvpr-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-hero {
				position: relative;
				margin-bottom: 26px;
				padding: 34px 24px;
				border-radius: 18px;
				background:
					radial-gradient(circle at top left, rgba(255, 255, 255, 0.22), transparent 34%),
					linear-gradient(135deg, #111 0%, #2a2a2a 55%, #444 100%);
				color: #fff;
				text-align: center;
				overflow: hidden;
				box-shadow: var(--cvpr-shadow);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-hero::after {
				content: "";
				position: absolute;
				right: -80px;
				bottom: -80px;
				width: 210px;
				height: 210px;
				border-radius: 50%;
				background: rgba(255, 255, 255, 0.08);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-title-wrap {
				position: relative;
				z-index: 1;
				max-width: 900px;
				margin: 0 auto;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-kicker {
				display: inline-block;
				margin-bottom: 10px;
				padding: 6px 12px;
				border: 1px solid rgba(255, 255, 255, 0.35);
				border-radius: 999px;
				font-size: 12px;
				font-weight: 800;
				letter-spacing: 0.16em;
				text-transform: uppercase;
				color: rgba(255, 255, 255, 0.86);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-title-wrap h2 {
				margin: 0;
				font-size: clamp(30px, 4vw, 48px);
				line-height: 1.05;
				font-weight: 900;
				letter-spacing: 0.08em;
				text-transform: uppercase;
				color: #fff;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-title-wrap p {
				max-width: 640px;
				margin: 14px auto 0;
				color: rgba(255, 255, 255, 0.82);
				font-size: 16px;
				line-height: 1.5;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-count {
				display: inline-flex;
				margin-top: 18px;
				padding: 8px 14px;
				border-radius: 999px;
				background: #fff;
				color: #111;
				font-size: 13px;
				font-weight: 800;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-toolbar {
				display: grid;
				grid-template-columns: minmax(260px, 420px) 1fr;
				gap: 16px;
				align-items: center;
				margin-bottom: 26px;
				padding: 16px;
				border: 1px solid var(--cvpr-border);
				border-radius: 16px;
				background: #fff;
				box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-search-wrap {
				position: relative;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-search-icon {
				position: absolute;
				left: 14px;
				top: 50%;
				transform: translateY(-50%);
				font-size: 15px;
				opacity: 0.7;
				pointer-events: none;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-search {
				width: 100%;
				padding: 13px 14px 13px 42px;
				border: 1px solid #ddd;
				border-radius: 999px;
				background: #f8f8f8;
				font-size: 15px;
				outline: none;
				transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-search:focus {
				border-color: #222;
				background: #fff;
				box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-letters {
				display: flex;
				flex-wrap: nowrap;
				gap: clamp(4px, 0.5vw, 7px);
				justify-content: flex-end;
				align-items: center;
				overflow-x: auto;
				overflow-y: hidden;
				white-space: nowrap;
				scrollbar-width: thin;
				-webkit-overflow-scrolling: touch;
				padding-bottom: 4px;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-letter {
				flex: 0 0 auto;
				min-width: clamp(30px, 2.4vw, 38px);
				height: clamp(32px, 2.6vw, 38px);
				padding: 0 clamp(8px, 0.8vw, 12px);
				border: 1px solid #ddd;
				border-radius: 999px;
				background: #fff;
				color: #222;
				font-size: 13px;
				font-weight: 800;
				cursor: pointer;
				transition: all 0.18s ease;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-letter:hover,
			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-letter.is-active {
				background: #111;
				border-color: #111;
				color: #fff;
				transform: translateY(-1px);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-grid {
				display: grid;
				grid-template-columns: repeat(var(--cvpr-auto-cols), minmax(0, 1fr));
				gap: 24px;
				align-items: start;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item {
				width: 100%;
				max-width: 358px;
				margin-left: auto;
				margin-right: auto;
				min-width: 0;
				padding: 10px;
				border: 1px solid transparent;
				border-radius: 16px;
				background: #fff;
				transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item:hover {
				transform: translateY(-3px);
				border-color: var(--cvpr-border);
				box-shadow: var(--cvpr-shadow);
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item .cvpr-card {
				width: 100%;
				max-width: 358px;
				margin: 0 auto;
			}

			/* Normalização das miniaturas: mantém o formato original 358x506 sem ultrapassar o tamanho padrão. */
			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item .cvpr-card__preview {
				width: 100%;
				aspect-ratio: 358 / 506;
				min-height: 0;
				height: auto;
				max-width: 358px;
				max-height: 506px;
				border-radius: 12px;
				overflow: hidden;
				background: #f5f5f5;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item .cvpr-card__cover-img,
			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item .cvpr-card__canvas {
				width: 100% !important;
				height: 100% !important;
				max-width: 358px;
				max-height: 506px;
				object-fit: contain;
				display: block;
				margin: 0 auto;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-item .cvpr-card__title {
				text-align: center;
				font-size: 15px;
				font-weight: 800;
				line-height: 1.3;
				min-height: 40px;
			}

			#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-empty {
				margin-top: 24px;
				padding: 16px 18px;
				border-radius: 12px;
				background: #f7f7f7;
				color: #666;
				font-size: 15px;
				text-align: center;
			}

			@media (max-width: 1600px) {
				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-grid {
					grid-template-columns: repeat(5, minmax(0, 1fr));
				}
			}

			@media (max-width: 1380px) {
				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-grid {
					grid-template-columns: repeat(4, minmax(0, 1fr));
				}
			}

			@media (max-width: 1100px) {
				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-grid {
					grid-template-columns: repeat(3, minmax(0, 1fr));
				}
			}

			@media (max-width: 900px) {
				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-toolbar {
					grid-template-columns: 1fr;
				}

				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-letters {
					justify-content: flex-start;
				}
			}

			@media (max-width: 768px) {
				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-grid {
					grid-template-columns: repeat(2, minmax(0, 1fr));
					gap: 18px;
				}

				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-hero {
					padding: 28px 18px;
				}
			}

			@media (max-width: 520px) {
				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-grid {
					grid-template-columns: 1fr;
					gap: 20px;
				}

				#<?php echo esc_attr( $uid ); ?> .cvpr-auto-catalogos-letter {
					min-width: 34px;
					height: 34px;
					padding: 0 10px;
					font-size: 12px;
				}
			}
		</style>

		<script>
			(function () {
				const root = document.getElementById('<?php echo esc_js( $uid ); ?>');

				if (!root) {
					return;
				}

				const input = root.querySelector('.cvpr-auto-catalogos-search');
				const items = root.querySelectorAll('.cvpr-auto-catalogos-item');
				const empty = root.querySelector('.cvpr-auto-catalogos-empty');
				const letters = root.querySelectorAll('.cvpr-auto-catalogos-letter');

				let activeLetter = 'todos';

				function normalizeText(value) {
					return String(value || '')
						.toLowerCase()
						.normalize('NFD')
						.replace(/[\u0300-\u036f]/g, '');
				}

				function applyFilters() {
					const query = input ? normalizeText(input.value) : '';
					let visible = 0;

					items.forEach(function (item) {
						const search = item.getAttribute('data-search') || '';
						const initial = item.getAttribute('data-initial') || '';

						const matchSearch = !query || search.indexOf(query) !== -1;
						const matchLetter = activeLetter === 'todos' || initial === activeLetter;

						if (matchSearch && matchLetter) {
							item.hidden = false;
							visible++;
						} else {
							item.hidden = true;
						}
					});

					if (empty) {
						empty.hidden = visible !== 0;
					}
				}

				if (input) {
					input.addEventListener('input', applyFilters);
				}

				letters.forEach(function (button) {
					button.addEventListener('click', function () {
						letters.forEach(function (btn) {
							btn.classList.remove('is-active');
						});

						button.classList.add('is-active');
						activeLetter = button.getAttribute('data-letter') || 'todos';

						applyFilters();
					});
				});
			})();
		</script>

		<?php
		return ob_get_clean();
	}
}


if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_mb_upper' ) ) {
	function cvpr2_pdf_catalogos_auto_mb_upper( $text ) {
		$text = (string) $text;

		if ( function_exists( 'mb_strtoupper' ) ) {
			return mb_strtoupper( $text, 'UTF-8' );
		}

		return strtoupper( remove_accents( $text ) );
	}
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_mb_lower' ) ) {
	function cvpr2_pdf_catalogos_auto_mb_lower( $text ) {
		$text = (string) $text;

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $text, 'UTF-8' );
		}

		return strtolower( remove_accents( $text ) );
	}
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_title_case_pt' ) ) {
	function cvpr2_pdf_catalogos_auto_title_case_pt( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		if ( '' === $text ) {
			return '';
		}

		if ( function_exists( 'mb_convert_case' ) && function_exists( 'mb_strtolower' ) ) {
			return mb_convert_case( mb_strtolower( $text, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
		}

		return ucwords( strtolower( remove_accents( $text ) ) );
	}
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_initial' ) ) {
	function cvpr2_pdf_catalogos_auto_initial( $name ) {
		$name = trim( remove_accents( (string) $name ) );

		if ( '' === $name ) {
			return '#';
		}

		$initial = strtoupper( substr( $name, 0, 1 ) );

		if ( preg_match( '/[A-Z]/', $initial ) ) {
			return $initial;
		}

		if ( preg_match( '/[0-9]/', $initial ) ) {
			return '0-9';
		}

		return '#';
	}
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_get_pdfs' ) ) {
	function cvpr2_pdf_catalogos_auto_get_pdfs( $folder = 'catalogos', $recursive = 'nao' ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return array();
		}

		$folder = trim( (string) $folder );
		$folder = trim( $folder, "/\\" );

		// Seguranca: nao permitir sair da pasta uploads.
		$folder = str_replace( array( '..', '\\' ), array( '', '/' ), $folder );

		if ( empty( $folder ) ) {
			$folder = 'catalogos';
		}

		$base_dir = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . $folder );
		$base_url = trailingslashit( $upload_dir['baseurl'] ) . str_replace( ' ', '%20', $folder );

		if ( ! is_dir( $base_dir ) || ! is_readable( $base_dir ) ) {
			return array();
		}

		$files = array();

		if ( 'sim' === strtolower( sanitize_text_field( $recursive ) ) ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'pdf' === strtolower( $file->getExtension() ) ) {
					$files[] = wp_normalize_path( $file->getPathname() );
				}
			}
		} else {
			try {
				$iterator = new DirectoryIterator( $base_dir );

				foreach ( $iterator as $file ) {
					if ( $file->isFile() && 'pdf' === strtolower( $file->getExtension() ) ) {
						$files[] = wp_normalize_path( $file->getPathname() );
					}
				}
			} catch ( Exception $e ) {
				$files = array();
			}
		}

		sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

		if ( empty( $files ) ) {
			return array();
		}

		$items    = array();
		$used_ids = array();

		foreach ( $files as $file_path ) {
			$file_path = wp_normalize_path( $file_path );

			if ( ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
				continue;
			}

			$relative_path = ltrim( str_replace( $base_dir, '', $file_path ), '/' );
			$file_url      = trailingslashit( $base_url ) . str_replace( '%2F', '/', rawurlencode( $relative_path ) );

			$file_name = basename( $file_path );
			$name      = cvpr2_pdf_catalogos_auto_title_from_filename( $file_name );
			$title     = cvpr2_pdf_catalogos_auto_title_label( $name );

			$id_base = sanitize_key( sanitize_title( $name ) );

			if ( empty( $id_base ) ) {
				$id_base = 'catalogo';
			}

			$id = $id_base;
			$i  = 2;

			while ( isset( $used_ids[ $id ] ) ) {
				$id = $id_base . '-' . $i;
				$i++;
			}

			$used_ids[ $id ] = true;

			$items[] = array(
				'id'       => $id,
				'nome'     => $name,
				'titulo'   => $title,
				'ficheiro' => $file_name,
				'url'      => esc_url_raw( $file_url, array( 'http', 'https' ) ),
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_title_from_filename' ) ) {
	function cvpr2_pdf_catalogos_auto_title_from_filename( $filename ) {
		$name = preg_replace( '/\.pdf$/i', '', (string) $filename );

		$name = rawurldecode( $name );
		$name = str_replace( array( '_', '-' ), ' ', $name );

		// Limpeza de termos comuns em nomes de ficheiros.
		$name = preg_replace(
			'/\b(PT|ES|WEB|CATALOGUE|CATALOGO|CATÁLOGO|CAT|MIOLO|RESUMIDO)\b/iu',
			'',
			$name
		);

		// Remove anos soltos como 2025, 2024, 2023.
		$name = preg_replace( '/\b20[0-9]{2}\b/', '', $name );

		$name = preg_replace( '/\s+/', ' ', $name );
		$name = trim( $name );

		if ( empty( $name ) ) {
			return 'Catálogo';
		}

		return cvpr2_pdf_catalogos_auto_title_case_pt( $name );
	}
}

if ( ! function_exists( 'cvpr2_pdf_catalogos_auto_title_label' ) ) {
	function cvpr2_pdf_catalogos_auto_title_label( $name ) {
		$name = trim( (string) $name );

		if ( preg_match( '/^(cat[aá]logo|folheto)/iu', $name ) ) {
			return $name;
		}

		return 'Catálogo ' . $name;
	}
}