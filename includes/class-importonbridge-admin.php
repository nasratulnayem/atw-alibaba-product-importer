<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImportonBridge_Admin {
	private static $hook_suffix = '';
	private static $legacy_hook_suffix = '';
	private static $url_import_hook_suffix = '';
	private static $rewriter_hook_suffix = '';
	private static $usage_hook_suffix = '';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_app_passwords_notice' ) );
		add_action( 'admin_post_importonbridge_enable_app_passwords', array( __CLASS__, 'handle_enable_app_passwords' ) );
	}

	public static function maybe_show_app_passwords_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'get_main_network_id' ) || ! function_exists( 'get_network_option' ) ) {
			return;
		}
		$in_use = (bool) get_network_option( get_main_network_id(), 'importonbridge_using_application_passwords' );
		if ( $in_use ) {
			return;
		}
		$action_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=importonbridge_enable_app_passwords' ),
			'importonbridge_enable_app_passwords'
		);
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Importon Bridge:', 'importon-bridge' ); ?></strong>
				<?php esc_html_e( 'Application Passwords are not yet enabled on this site. The browser companion will not be able to authenticate until they are enabled.', 'importon-bridge' ); ?>
				<a href="<?php echo esc_url( $action_url ); ?>" class="button button-secondary" style="margin-left:8px;">
					<?php esc_html_e( 'Enable Application Passwords', 'importon-bridge' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public static function handle_enable_app_passwords(): void {
		check_admin_referer( 'importonbridge_enable_app_passwords' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'importon-bridge' ) );
		}
		if ( function_exists( 'get_main_network_id' ) && function_exists( 'update_network_option' ) ) {
			update_network_option( get_main_network_id(), 'importonbridge_using_application_passwords', 1 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=importon-bridge&importonbridge_app_pw_enabled=1' ) );
		exit;
	}

	public static function admin_menu(): void {
		$cap = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';

		self::$hook_suffix = (string) add_menu_page(
			'Importon Bridge',
			'Importon Bridge',
			$cap,
			'importon-bridge',
			array( __CLASS__, 'render_page' ),
			'dashicons-store',
			56
		);

		// Settings submenu (same as parent page)
		add_submenu_page(
			'importon-bridge',
			__( 'Connect', 'importon-bridge' ),
			__( 'Connect', 'importon-bridge' ),
			$cap,
			'importon-bridge',
			array( __CLASS__, 'render_page' )
		);

		self::$url_import_hook_suffix = (string) add_submenu_page(
			'importon-bridge',
			__( 'URL Import', 'importon-bridge' ),
			__( 'URL Import', 'importon-bridge' ),
			$cap,
			'importonbridge-url-import',
			array( __CLASS__, 'render_url_import_page' )
		);

		self::$legacy_hook_suffix = (string) add_submenu_page(
			null,
			__( 'Alibaba Import', 'importon-bridge' ),
			__( 'Alibaba Import', 'importon-bridge' ),
			$cap,
			'importonbridge-alibaba-import',
			array( __CLASS__, 'render_legacy_redirect' )
		);

		self::$rewriter_hook_suffix = (string) add_submenu_page(
			'importon-bridge',
			__( 'Rewriter', 'importon-bridge' ),
			__( 'Rewriter', 'importon-bridge' ),
			$cap,
			'importonbridge-rewriter',
			array( __CLASS__, 'render_rewriter_page' )
		);

		self::$usage_hook_suffix = (string) add_submenu_page(
			'importon-bridge',
			__( 'Usage', 'importon-bridge' ),
			__( 'Usage', 'importon-bridge' ),
			$cap,
			'importonbridge-usage',
			array( __CLASS__, 'render_usage_page' )
		);

		// Freemius Upgrade visible on all pages - no clone
	}

	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( self::$hook_suffix, self::$legacy_hook_suffix, self::$url_import_hook_suffix, self::$rewriter_hook_suffix, self::$usage_hook_suffix, 'importon-bridge_page_importon-bridge-pricing', 'importon-bridge_page_importon-bridge-account' ), true ) ) {
			return;
		}

		wp_register_style( 'importonbridge_admin', false, array(), IMPORTONBRIDGE_VERSION );
		wp_enqueue_style( 'importonbridge_admin' );
		wp_add_inline_style( 'importonbridge_admin', self::get_common_admin_css() );


		if ( $hook_suffix === self::$url_import_hook_suffix ) {
			wp_enqueue_script(
				'importonbridge_url_import_admin',
				plugin_dir_url( IMPORTONBRIDGE_PLUGIN_FILE ) . 'assets/url-import-admin.js',
				array(),
				IMPORTONBRIDGE_VERSION,
				true
			);

			$user = wp_get_current_user();
			wp_localize_script(
				'importonbridge_url_import_admin',
				'importonbridgeUrlImportData',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => ImportonBridge_Url_Import::get_ajax_nonce(),
					'restNonce'    => wp_create_nonce( 'wp_rest' ),
					'categories'   => ImportonBridge_Url_Import::get_categories(),
					'latestRun'    => ImportonBridge_Url_Import::get_latest_run(),
					'recentRuns'   => ImportonBridge_Url_Import::get_recent_runs( 8, false ),
					'siteBaseUrl'  => home_url( '/' ),
					'currentUser'  => $user instanceof WP_User ? (string) $user->user_login : '',
					'settingsUrl'  => admin_url( 'admin.php?page=importon-bridge' ),
				)
			);
		}

	}

	public static function render_legacy_redirect(): void {
		self::assert_access();

		wp_safe_redirect( admin_url( 'admin.php?page=importon-bridge' ) );
		exit;
	}

	public static function render_page(): void {
		self::assert_access();

		$site_url     = rtrim( home_url( '/' ), '/' );
		$current_user = wp_get_current_user();
		$download_url = 'https://github.com/nasratulnayem/importon-bridge/releases/download/v0.2.0/importon-bridge-extension.zip';
		$rest_nonce   = wp_create_nonce( 'wp_rest' );
		$rest_url     = rest_url( 'importonbridge/v1/' );


		?>
		<div class="wrap importonbridge-wrap importonbridge-shell importonbridge-page">
			<meta name="importonbridge-url-import-bridge" content="1">

			<div class="importonbridge-modal-overlay" id="importonbridge-terms-modal-overlay" style="display:none;">
				<div class="importonbridge-modal" style="max-width:560px;">
					<div class="importonbridge-modal-header">
						<div class="importonbridge-modal-icon" style="background:#eff6ff;color:#2563eb;animation:none;">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
						</div>
						<h3 style="margin:0;font-size:18px;font-weight:700;color:#0f172a;">Terms and Conditions</h3>
					</div>
					<div class="importonbridge-modal-body" style="text-align:left;font-size:14px;line-height:1.7;color:#374151;">
						<p style="margin:0 0 16px;">By connecting Importon Bridge to your WordPress site, you agree to the following:</p>
						<ul style="padding:0;margin:0;list-style:none;display:grid;gap:12px;">
							<li style="padding:12px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><strong style="color:#1e293b;display:block;margin-bottom:3px;">Authentication</strong><span style="color:#475569;">Your site URL and WordPress username are used solely to authenticate with the browser companion extension.</span></li>
							<li style="padding:12px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><strong style="color:#1e293b;display:block;margin-bottom:3px;">Application Passwords</strong><span style="color:#475569;">A secure application password is generated for the extension. It is stored only in your browser's extension storage and can be revoked at any time from your WordPress profile.</span></li>
							<li style="padding:12px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><strong style="color:#1e293b;display:block;margin-bottom:3px;">Data Transfer</strong><span style="color:#475569;">Product data you import flows directly from the websites you browse to your WordPress site via REST API. No data is sent to third-party servers.</span></li>
							<li style="padding:12px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><strong style="color:#1e293b;display:block;margin-bottom:3px;">API Keys</strong><span style="color:#475569;">Any AI provider API keys you configure (OpenAI, Gemini) are stored in your WordPress database and sent directly to the respective provider when rewriting product content.</span></li>
							<li style="padding:12px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><strong style="color:#1e293b;display:block;margin-bottom:3px;">Security</strong><span style="color:#475569;">Keep your API keys and application passwords confidential. Revoke them immediately if you suspect unauthorized use.</span></li>
							<li style="padding:12px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><strong style="color:#1e293b;display:block;margin-bottom:3px;">GDPR</strong><span style="color:#475569;">You are responsible for ensuring your use of this plugin complies with applicable data protection regulations.</span></li>
						</ul>
						<p style="margin:16px 0 0;color:#64748b;font-size:13px;">By checking the agreement box and clicking <strong style="color:#1e293b;">Connect</strong>, you accept these terms.</p>
					</div>
					<div class="importonbridge-modal-footer" style="display:flex;">
						<button type="button" class="importonbridge-btn-primary" id="importonbridge-terms-close-btn" style="animation:none;background:#0f172a;padding:10px 28px;font-size:14px;font-weight:600;">Got it</button>
					</div>
				</div>
			</div>

			<?php if ( function_exists( "ib_fs" ) && ib_fs()->is_registered() ) : ?>
			<div class="importonbridge-connect-hero" id="importonbridge-download-hero">
				<div class="importonbridge-hero-icon">
					<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
				</div>
				<h1>Importon Bridge</h1>
				<p class="importonbridge-hero-sub">Download the browser companion, then connect to start importing products from any supplier.</p>

				<div class="importonbridge-hero-actions">
					<a href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer" class="importonbridge-btn-primary importonbridge-btn-primary--neutral" id="importonbridge-download-btn">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
						Download Extension
					</a>
					<button type="button" class="importonbridge-btn-primary importonbridge-btn-primary--danger" id="importonbridge-connect-btn" disabled>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
						Connect
					</button>
				</div>

				<label class="importonbridge-terms-checkbox" id="importonbridge-terms-label">
					<input type="checkbox" id="importonbridge-terms-checkbox">
					<span class="importonbridge-terms-text">I agree to the <a href="#" id="importonbridge-terms-link">terms and conditions</a></span>
				</label>
			</div>
			<?php endif; ?>

			<div class="importonbridge-modal-overlay" id="importonbridge-modal-overlay" style="display:none;">
				<div class="importonbridge-modal">
					<div class="importonbridge-modal-header">
						<div class="importonbridge-modal-icon" id="importonbridge-modal-icon">
							<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						</div>
						<h3 id="importonbridge-modal-title">Connecting...</h3>
					</div>
					<div class="importonbridge-modal-body">
						<div class="importonbridge-steps" id="importonbridge-steps">
							<div class="importonbridge-step" data-step="init">
								<div class="importonbridge-step-badge" id="step-badge-init">1</div>
								<div class="importonbridge-step-content">
									<div class="importonbridge-step-title" id="step-title-init">Initializing connection</div>
									<div class="importonbridge-step-desc" id="step-desc-init">Preparing to connect to the WordPress site...</div>
								</div>
							</div>
							<div class="importonbridge-step" data-step="app_password">
								<div class="importonbridge-step-badge" id="step-badge-app_password">2</div>
								<div class="importonbridge-step-content">
									<div class="importonbridge-step-title" id="step-title-app_password">Creating application password</div>
									<div class="importonbridge-step-desc" id="step-desc-app_password">Generating secure credentials for the extension...</div>
								</div>
							</div>
							<div class="importonbridge-step" data-step="bridge">
								<div class="importonbridge-step-badge" id="step-badge-bridge">3</div>
								<div class="importonbridge-step-content">
									<div class="importonbridge-step-title" id="step-title-bridge">Connecting to browser extension</div>
									<div class="importonbridge-step-desc" id="step-desc-bridge">Sending credentials to the browser companion...</div>
								</div>
							</div>
							<div class="importonbridge-step" data-step="categories">
								<div class="importonbridge-step-badge" id="step-badge-categories">4</div>
								<div class="importonbridge-step-content">
									<div class="importonbridge-step-title" id="step-title-categories">Fetching categories</div>
									<div class="importonbridge-step-desc" id="step-desc-categories">Loading WooCommerce product categories...</div>
								</div>
							</div>
						</div>
					</div>
					<div class="importonbridge-modal-footer" id="importonbridge-modal-footer" style="display:none;">
						<button type="button" class="importonbridge-btn-primary" id="importonbridge-modal-ok-btn" style="animation:none;">Done</button>
					</div>
				</div>
			</div>

			<div class="importonbridge-connect-main" id="importonbridge-main-section" style="display:none;">
				<div class="importonbridge-card importonbridge-card--connect">
					<div class="importonbridge-connect-header">
						<span class="importonbridge-status-dot" id="importonbridge-status-dot"></span>
						<span class="importonbridge-status-label" id="importonbridge-connection-badge">Disconnected</span>
					</div>

					<div class="importonbridge-connect-body">
						<div class="importonbridge-connect-info" id="importonbridge-connection-details" style="display:none;">
							<div class="importonbridge-info-row">
								<span class="importonbridge-info-label">Site URL</span>
								<code class="importonbridge-info-value"><?php echo esc_html( $site_url ); ?></code>
							</div>
							<div class="importonbridge-info-row">
								<span class="importonbridge-info-label">Authenticated as</span>
								<span class="importonbridge-info-value"><strong><?php echo esc_html( $current_user->user_login ); ?></strong></span>
							</div>
							<div class="importonbridge-info-row">
								<span class="importonbridge-info-label">Extension</span>
								<span class="importonbridge-info-value" id="importonbridge-extension-status">Not detected</span>
							</div>
						</div>

						<div class="importonbridge-connect-actions">
							<button type="button" class="importonbridge-btn-primary importonbridge-btn-primary--danger" id="importonbridge-reconnect-btn" disabled>
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
								Connect
							</button>
							<button type="button" class="importonbridge-btn-secondary" id="importonbridge-disconnect-btn" style="display:none;">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
								Disconnect
							</button>
						</div>

						<label class="importonbridge-terms-checkbox" id="importonbridge-terms-label-main">
							<input type="checkbox" id="importonbridge-terms-checkbox-main">
							<span class="importonbridge-terms-text">I agree to the <a href="#" id="importonbridge-terms-link-main">terms and conditions</a></span>
						</label>

					</div>

					<div class="importonbridge-connect-footer">
						<button type="button" class="importonbridge-btn-text" id="importonbridge-reset-btn">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
							Reset Connection
						</button>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var REST_URL = <?php echo json_encode( $rest_url ); ?>;
			var REST_NONCE = <?php echo json_encode( $rest_nonce ); ?>;
			var WP_BASE_URL = <?php echo json_encode( $site_url ); ?>;
			var WP_USER = <?php echo json_encode( $current_user->user_login ); ?>;
			var DOWNLOAD_KEY = 'importonbridge_downloaded_once_v1';
			var CONNECTED_KEY = 'importonbridge_connected_once_v1';
			var EXT_READY = 'IMPORTONBRIDGE_URL_IMPORT_BRIDGE_READY';
			var REQ_TYPE = 'IMPORTONBRIDGE_URL_IMPORT_BRIDGE_REQUEST';
			var RES_TYPE = 'IMPORTONBRIDGE_URL_IMPORT_BRIDGE_RESPONSE';
			var bridgeReady = false;
			var restoringNow = false;

			function qs(id) { return document.getElementById(id); }

			function stored(key) {
				try { return localStorage.getItem(key) === '1'; } catch { return false; }
			}

			function store(key, val) {
				try { if (val) localStorage.setItem(key, '1'); else localStorage.removeItem(key); } catch {}
			}

			// ── Terms modal ─────────────────────────────────────────────────────
			function openTermsModal() {
				var overlay = qs('importonbridge-terms-modal-overlay');
				if (overlay) overlay.style.display = 'flex';
			}

			qs('importonbridge-terms-link') && qs('importonbridge-terms-link').addEventListener('click', function(e) {
				e.preventDefault();
				openTermsModal();
			});

			qs('importonbridge-terms-link-main') && qs('importonbridge-terms-link-main').addEventListener('click', function(e) {
				e.preventDefault();
				openTermsModal();
			});

			qs('importonbridge-terms-close-btn') && qs('importonbridge-terms-close-btn').addEventListener('click', function() {
				var overlay = qs('importonbridge-terms-modal-overlay');
				if (overlay) overlay.style.display = 'none';
			});

			// ── Terms checkbox ────────────────────────────────────────────────
			function syncTermsState() {
				var heroTerms = qs('importonbridge-terms-checkbox');
				var mainTerms = qs('importonbridge-terms-checkbox-main');
				var heroBtn = qs('importonbridge-connect-btn');
				var mainBtn = qs('importonbridge-reconnect-btn');
				var checked = (heroTerms && heroTerms.checked) || (mainTerms && mainTerms.checked);
				if (heroBtn) heroBtn.disabled = !checked;
				if (mainBtn) mainBtn.disabled = !checked;
			}

			var termsCheckbox = qs('importonbridge-terms-checkbox');
			var termsCheckboxMain = qs('importonbridge-terms-checkbox-main');
			if (termsCheckbox) {
				termsCheckbox.addEventListener('change', function() {
					if (termsCheckboxMain) termsCheckboxMain.checked = this.checked;
					syncTermsState();
				});
			}
			if (termsCheckboxMain) {
				termsCheckboxMain.addEventListener('change', function() {
					if (termsCheckbox) termsCheckbox.checked = this.checked;
					syncTermsState();
				});
			}
			syncTermsState();

			// ── Modal ─────────────────────────────────────────────────────────
			var stepsOrder = ['init', 'app_password', 'bridge', 'categories'];

			function openModal() {
				var overlay = qs('importonbridge-modal-overlay');
				if (overlay) overlay.style.display = 'flex';
			}

			function closeModal() {
				var overlay = qs('importonbridge-modal-overlay');
				if (overlay) overlay.style.display = 'none';
			}

			function setStep(stepId, state, title, desc) {
				var stepEl = document.querySelector('.importonbridge-step[data-step="' + stepId + '"]');
				if (!stepEl) return;
				var badge = qs('step-badge-' + stepId);
				var titleEl = qs('step-title-' + stepId);
				var descEl = qs('step-desc-' + stepId);

				stepEl.className = 'importonbridge-step';
				if (state === 'active') {
					stepEl.classList.add('importonbridge-step--active');
					if (badge) badge.textContent = '';
				} else if (state === 'done') {
					stepEl.classList.add('importonbridge-step--done');
					if (badge) badge.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
				} else if (state === 'warning') {
					stepEl.classList.add('importonbridge-step--warning');
					if (badge) badge.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
				} else if (state === 'error') {
					stepEl.classList.add('importonbridge-step--error');
					if (badge) badge.textContent = '!';
				} else {
					stepEl.classList.add('importonbridge-step--pending');
				}

				if (title && titleEl) titleEl.textContent = title;
				if (desc && descEl) descEl.textContent = desc;

				// Auto-scroll to active step
				if (state === 'active' && stepEl.scrollIntoView) {
					stepEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}
			}

			function resetSteps() {
				stepsOrder.forEach(function(s) {
					setStep(s, '', '');
				});
				qs('importonbridge-step-init') && setStep('init', 'active', 'Initializing connection', 'Preparing to connect to the WordPress site...');
				qs('importonbridge-step-app_password') && setStep('app_password', '', 'Creating application password', 'Generating secure credentials for the extension...');
				qs('importonbridge-step-bridge') && setStep('bridge', '', 'Connecting to browser extension', 'Sending credentials to the browser companion...');
				qs('importonbridge-step-categories') && setStep('categories', '', 'Fetching categories', 'Loading WooCommerce product categories...');
			}

			function showModalSuccess() {
				var icon = qs('importonbridge-modal-icon');
				var title = qs('importonbridge-modal-title');
				var footer = qs('importonbridge-modal-footer');
				if (icon) icon.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
				if (title) title.textContent = 'Connected Successfully';
				if (footer) footer.style.display = 'flex';
			}

			function showModalWarning() {
				var icon = qs('importonbridge-modal-icon');
				var title = qs('importonbridge-modal-title');
				var footer = qs('importonbridge-modal-footer');
				if (icon) icon.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
				if (title) title.textContent = 'Waiting for browser extension';
				if (footer) footer.style.display = 'flex';
			}

			function showModalError(msg) {
				var icon = qs('importonbridge-modal-icon');
				var title = qs('importonbridge-modal-title');
				var footer = qs('importonbridge-modal-footer');
				if (icon) icon.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
				if (title) title.textContent = msg || 'Connection Failed';
				if (footer) footer.style.display = 'flex';
			}

			qs('importonbridge-modal-ok-btn') && qs('importonbridge-modal-ok-btn').addEventListener('click', function() {
				closeModal();
				if (stored(CONNECTED_KEY)) {
					setUI(true, 'Ready');
				}
			});

			// ── showMain (was missing) ────────────────────────────────────────
			function showMain() {
				var hero = qs('importonbridge-download-hero');
				var main = qs('importonbridge-main-section');
				if (hero) hero.style.display = 'none';
				if (main) main.style.display = 'block';
			}

			function showHero() {
				var hero = qs('importonbridge-download-hero');
				var main = qs('importonbridge-main-section');
				if (hero) hero.style.display = 'block';
				if (main) main.style.display = 'none';
			}

			// ── UI helpers ────────────────────────────────────────────────────
			function setUI(ok, msg, _cats) {
				var badge = qs('importonbridge-connection-badge');
				var dot = qs('importonbridge-status-dot');
				var details = qs('importonbridge-connection-details');
				var extStatus = qs('importonbridge-extension-status');
				var reconnectBtn = qs('importonbridge-reconnect-btn');
				var disconnectBtn = qs('importonbridge-disconnect-btn');

				var fullyConnected = ok && bridgeReady;

				if (badge) {
					if (fullyConnected) {
						badge.textContent = 'Connected';
						badge.style.color = '#059669';
					} else if (ok) {
						badge.textContent = 'Waiting for extension';
						badge.style.color = '#d97706';
					} else {
						badge.textContent = 'Disconnected';
						badge.style.color = '#64748b';
					}
				}
				if (dot) {
					if (fullyConnected) {
						dot.style.background = '#22c55e';
						dot.style.boxShadow = '0 0 0 3px rgba(34,197,94,0.2)';
					} else if (ok) {
						dot.style.background = '#d97706';
						dot.style.boxShadow = '0 0 0 3px rgba(217,119,6,0.2)';
					} else {
						dot.style.background = '#94a3b8';
						dot.style.boxShadow = 'none';
					}
				}
				if (extStatus) {
					extStatus.textContent = bridgeReady ? 'Connected' : 'Not detected';
					extStatus.style.color = bridgeReady ? '#059669' : '#94a3b8';
				}
				if (details) details.style.display = ok ? 'block' : 'none';
				if (reconnectBtn) reconnectBtn.style.display = ok ? 'none' : 'inline-flex';
				if (disconnectBtn) disconnectBtn.style.display = ok ? 'inline-flex' : 'none';

				var termsLabel = qs('importonbridge-terms-label');
				var termsLabelMain = qs('importonbridge-terms-label-main');
				if (termsLabel) termsLabel.style.display = ok ? 'none' : 'inline-flex';
				if (termsLabelMain) termsLabelMain.style.display = ok ? 'none' : 'inline-flex';

				store(CONNECTED_KEY, !!ok);
				if (ok) store(DOWNLOAD_KEY, true);
			}

			function postToBridge(cmd, payload) {
				return new Promise(function(resolve, reject) {
					if (!bridgeReady) {
						reject(new Error('Extension bridge not detected.'));
						return;
					}
					var id = 'ib_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
					var timeout = setTimeout(function() {
						window.removeEventListener('message', handler);
						reject(new Error('Bridge response timeout.'));
					}, 8000);
					function handler(event) {
						if (event.source !== window) return;
						var d = event.data || {};
						if (d.type !== RES_TYPE || d.requestId !== id) return;
						clearTimeout(timeout);
						window.removeEventListener('message', handler);
						if (d.ok) resolve(d.payload || {});
						else reject(new Error(d.error || 'Bridge request failed.'));
					}
					window.addEventListener('message', handler);
					window.postMessage({ type: REQ_TYPE, requestId: id, cmd: cmd, payload: payload || {} }, window.location.origin);
				});
			}

			// ── API calls ──────────────────────────────────────────────────────
			async function apiPost(endpoint) {
				var resp = await fetch(REST_URL + endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': REST_NONCE, 'Accept': 'application/json' }
				});
				return await resp.json();
			}

			async function apiGet(endpoint) {
				var resp = await fetch(REST_URL + endpoint, {
					method: 'GET',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': REST_NONCE, 'Accept': 'application/json' }
				});
				return await resp.json();
			}

			async function notifyExtension(data) {
				if (bridgeReady && data && data.app_password) {
					try {
						var p = {
							wpBaseUrl: data.site_url,
							wpUser: data.username,
							wpAppPassword: data.app_password
						};
						if (data.categories) p.categories = data.categories;
						await postToBridge('connect_bridge', p);
					} catch (e) {}
				}
			}

			// ── Silent restore (page load, no modal) ───────────────────────────
			// Uses /ping — does NOT create a new app password (unlike /init-bridge)
			// Shows "Connected" only when the extension is actually detected.
			async function restoreConnection() {
				if (restoringNow) return true;
				restoringNow = true;
				try {
					var data = await apiGet('ping');
					if (data && data.ok) {
						setUI(true, 'Ready', data.categories || []);
						return true;
					}
					setUI(false, 'Disconnected. Click Connect to start.', []);
					return false;
				} catch (e) {
					setUI(false, 'Disconnected.', []);
					return false;
				} finally {
					restoringNow = false;
				}
			}

			// ── Full connection with progress modal ────────────────────────────
			async function initConnection() {
				setUI(false, 'Connecting...', []);
				openModal();
				resetSteps();

				try {
					setStep('init', 'active', 'Initializing connection', 'Contacting the WordPress REST API...');
					setStep('init', 'done', 'Initializing connection', 'Connected to REST API.');

					setStep('app_password', 'active', 'Application password', 'Generating secure credentials...');

					var data = await apiPost('init-bridge');

					if (!data || !data.ok) {
						setStep('app_password', 'error', 'Application password failed', data && data.message ? data.message : 'Could not create application password.');
						showModalError(data && data.message ? data.message : 'Connection failed.');
						setUI(false, data && data.message ? data.message : 'Connection failed.', []);
						return false;
					}

					setStep('app_password', 'done', 'Application password created', 'Secure credentials generated successfully.');

					var extensionReached = false;

					if (bridgeReady) {
						setStep('bridge', 'active', 'Connecting to browser extension', 'Sending credentials to the browser companion...');
						try {
							var payload = {
								wpBaseUrl: data.site_url,
								wpUser: data.username
							};
							if (data.app_password) {
								payload.wpAppPassword = data.app_password;
							}
							if (data.categories) {
								payload.categories = data.categories;
							}
							await postToBridge('connect_bridge', payload);
							extensionReached = true;
							setStep('bridge', 'done', 'Browser extension connected', 'Credentials sent successfully.');
						} catch (e) {
							setStep('bridge', 'warning', 'Browser extension', 'Extension not detected. Connection will resume when the extension is active.');
						}
					} else {
						setStep('bridge', 'warning', 'Browser extension', 'Extension not detected. Connection will resume when the extension is active.');
					}

					setStep('categories', 'active', 'Fetching categories', 'Loading WooCommerce product categories...');
					setStep('categories', 'done', 'Categories loaded', (data.categories ? data.categories.length : 0) + ' product categories found.');

					if (extensionReached) {
						showModalSuccess();
						setUI(true, 'Ready', data.categories || []);
					} else {
						showModalWarning();
						setUI(true, 'Ready', data.categories || []);
					}
					return true;

				} catch (e) {
					setStep('app_password', 'error', 'Connection failed', e.message || 'An unexpected error occurred.');
					showModalError(e.message || 'Connection failed.');
					setUI(false, e.message || 'Connection failed.', []);
					return false;
				}
			}

			async function disconnect() {
				setUI(false, 'Disconnected.', []);
				if (bridgeReady) {
					try {
						await postToBridge('disconnect_bridge', {});
					} catch (e) {}
				}
				store(CONNECTED_KEY, false);
			}

			// ── Event handlers ────────────────────────────────────────────────

			qs('importonbridge-connect-btn').addEventListener('click', async function() {
				var btn = this;
				btn.disabled = true;
				btn.textContent = 'Connecting...';
				showMain();
				await initConnection();
				btn.disabled = !qs('importonbridge-terms-checkbox').checked;
				btn.textContent = 'Connect';
			});

			qs('importonbridge-reconnect-btn').addEventListener('click', async function() {
				var btn = this;
				btn.disabled = true;
				btn.textContent = 'Connecting...';
				await initConnection();
				syncTermsState();
				btn.textContent = 'Connect';
			});

			qs('importonbridge-disconnect-btn').addEventListener('click', function() {
				disconnect();
				syncTermsState();
			});

			qs('importonbridge-download-btn').addEventListener('click', function() {
				store(DOWNLOAD_KEY, true);
				store(CONNECTED_KEY, false);
				setTimeout(showMain, 200);
			});

			qs('importonbridge-reset-btn').addEventListener('click', function() {
				var btn = this;
				btn.disabled = true;
				btn.textContent = 'Resetting...';
				apiPost('disconnect')['finally'](function() {
					store(DOWNLOAD_KEY, false);
					store(CONNECTED_KEY, false);
					try { localStorage.removeItem('importonbridge_downloaded_once_v1'); localStorage.removeItem('importonbridge_connected_once_v1'); } catch(e) {}
					if (bridgeReady) {
						postToBridge('disconnect_bridge', {}).catch(function() {});
					}
					showHero();
					var termsH = qs('importonbridge-terms-checkbox');
					var termsM = qs('importonbridge-terms-checkbox-main');
					var termsLabel = qs('importonbridge-terms-label');
					if (termsLabel) termsLabel.style.display = '';
					if (termsH) termsH.checked = false;
					if (termsM) termsM.checked = false;
					syncTermsState();
				});
			});

			window.addEventListener('message', function(event) {
				if (event.source !== window) return;
				var data = event.data || {};
				if (data.type === EXT_READY) {
					bridgeReady = true;
					if (stored(CONNECTED_KEY)) {
						var _dot = qs('importonbridge-status-dot');
						var _badge = qs('importonbridge-connection-badge');
						if (_dot) { _dot.style.background = '#22c55e'; _dot.style.boxShadow = '0 0 0 3px rgba(34,197,94,0.2)'; }
						if (_badge) { _badge.textContent = 'Connected'; _badge.style.color = '#059669'; }
					}
					var extStatus = qs('importonbridge-extension-status');
					if (extStatus) {
						extStatus.textContent = 'Connected';
						extStatus.style.color = '#059669';
					}
				}
			});

			// ── Initial state ─────────────────────────────────────────────────
			syncTermsState();
			if (stored(DOWNLOAD_KEY)) {
				showMain();
				if (stored(CONNECTED_KEY)) {
					var b = qs('importonbridge-connection-badge');
					if (b) b.textContent = 'Checking...';
					var _r = qs('importonbridge-reconnect-btn');
					var _t = qs('importonbridge-terms-label');
					var _tm = qs('importonbridge-terms-label-main');
					if (_r) _r.style.display = 'none';
					if (_t) _t.style.display = 'none';
					if (_tm) _tm.style.display = 'none';
					restoreConnection();
				} else {
					setUI(false, 'Disconnected. Click Connect to start.', []);
				}
			}
		})();
		</script>
		<?php
	}

	public static function render_rewriter_page(): void {
		self::assert_access();

		$state = self::handle_settings_postback();
		?>
		<div class="importonbridge-wrap importonbridge-shell importonbridge-page">
			<div class="importonbridge-hero">
				<div class="importonbridge-hero-copy">
					<h1>AI Rewriter</h1>
					<p>Configure AI providers, models, and rewrite rules for imported products.</p>
				</div>
			</div>

			<?php if ( $state['ai_notice'] !== '' ) : ?>
				<div class="importonbridge-alert importonbridge-alert--success" style="margin-bottom:16px;"><?php echo esc_html( $state['ai_notice'] ); ?></div>
			<?php endif; ?>
			<?php if ( $state['ai_error'] !== '' ) : ?>
				<div class="importonbridge-alert importonbridge-alert--danger" style="margin-bottom:16px;"><?php echo esc_html( $state['ai_error'] ); ?></div>
			<?php endif; ?>

			<form method="post" class="importonbridge-form-stack">
				<?php wp_nonce_field( 'importonbridge_save_ai_settings_action', 'importonbridge_save_ai_settings_nonce' ); ?>

				<div class="importonbridge-ai-summary" style="margin-bottom:20px;">
					<div class="importonbridge-ai-summary-item">
						<span class="importonbridge-ai-summary-label">Rewrite</span>
						<strong class="importonbridge-ai-summary-value"><?php echo $state['ai_enabled'] ? esc_html__( 'Enabled', 'importon-bridge' ) : esc_html__( 'Disabled', 'importon-bridge' ); ?></strong>
					</div>
					<div class="importonbridge-ai-summary-item">
						<span class="importonbridge-ai-summary-label">Provider</span>
						<strong class="importonbridge-ai-summary-value"><?php echo 'gemini_first' === $state['ai_provider_order'] ? esc_html__( 'Gemini → OpenAI', 'importon-bridge' ) : esc_html__( 'OpenAI → Gemini', 'importon-bridge' ); ?></strong>
					</div>
					<div class="importonbridge-ai-summary-item">
						<span class="importonbridge-ai-summary-label">OpenAI</span>
						<strong class="importonbridge-ai-summary-value"><?php echo $state['ai_openai_key_saved'] ? esc_html__( 'Ready', 'importon-bridge' ) : esc_html__( 'Not Set', 'importon-bridge' ); ?></strong>
					</div>
					<div class="importonbridge-ai-summary-item">
						<span class="importonbridge-ai-summary-label">Gemini</span>
						<strong class="importonbridge-ai-summary-value"><?php echo $state['ai_gemini_key_saved'] ? esc_html__( 'Ready', 'importon-bridge' ) : esc_html__( 'Not Set', 'importon-bridge' ); ?></strong>
					</div>
				</div>

				<details class="importonbridge-accordion" style="margin-bottom:12px;">
					<summary>
						<div class="importonbridge-accordion-copy">
							<span class="importonbridge-accordion-title">General</span>
							<span class="importonbridge-accordion-meta">Enable rewriting and choose provider order.</span>
						</div>
					</summary>
					<div class="importonbridge-accordion-body">
						<div class="importonbridge-kv">
							<div class="importonbridge-k">Enable AI Rewrite</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_ai_enabled" value="1" <?php checked( $state['ai_enabled'] ); ?>>
									<span>Allow AI rewrite during import when a valid provider is configured</span>
								</label>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Rewrite Title</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_rewrite_title" value="1" <?php checked( $state['ai_rewrite_title'] ); ?>>
									<span>Rewrite imported product title with AI</span>
								</label>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Rewrite Description</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_rewrite_description" value="1" <?php checked( $state['ai_rewrite_description'] ); ?>>
									<span>Rewrite imported short and long descriptions with AI</span>
								</label>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Provider Order</div>
							<div class="importonbridge-v">
								<select name="importonbridge_ai_provider_order">
									<option value="openai_first" <?php selected( $state['ai_provider_order'], 'openai_first' ); ?>>OpenAI first, Gemini fallback</option>
									<option value="gemini_first" <?php selected( $state['ai_provider_order'], 'gemini_first' ); ?>>Gemini first, OpenAI fallback</option>
								</select>
								<div class="importonbridge-field-help">If both keys are available, URL Import tries the first provider and falls back automatically if it fails.</div>
							</div>
						</div>
					</div>
				</details>

				<details class="importonbridge-accordion" style="margin-bottom:12px;">
					<summary>
						<div class="importonbridge-accordion-copy">
							<span class="importonbridge-accordion-title">OpenAI</span>
							<span class="importonbridge-accordion-meta"><?php echo $state['ai_openai_key_saved'] ? esc_html__( 'Key saved', 'importon-bridge' ) : esc_html__( 'Add key and model', 'importon-bridge' ); ?> · <?php echo esc_html( $state['ai_openai_model'] ); ?></span>
						</div>
					</summary>
					<div class="importonbridge-accordion-body">
						<div class="importonbridge-kv">
							<div class="importonbridge-k">API Key</div>
							<div class="importonbridge-v importonbridge-inline-control">
								<input type="password" name="importonbridge_ai_openai_api_key" placeholder="<?php echo $state['ai_openai_key_saved'] ? 'OpenAI key saved - leave blank to keep existing' : 'sk-proj-...'; ?>" autocomplete="new-password" style="flex:1;min-width:200px;">
								<?php if ( $state['ai_openai_key_saved'] ) : ?>
									<span class="importonbridge-inline-badge importonbridge-inline-badge--success">&#10003; Key saved</span>
								<?php endif; ?>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Model</div>
							<div class="importonbridge-v">
								<?php
								$openai_models = array(
									'gpt-4o-mini'    => 'gpt-4o-mini (cheapest, recommended)',
									'gpt-4.1-nano'   => 'gpt-4.1-nano (cheapest 4.1)',
									'gpt-4.1'        => 'gpt-4.1',
									'gpt-4.1-mini'   => 'gpt-4.1-mini',
									'gpt-4o'         => 'gpt-4o',
									'gpt-4o-mini-2025-01-20' => 'gpt-4o-mini-2025-01-20',
								);
								?>
								<select name="importonbridge_ai_openai_model">
									<?php foreach ( $openai_models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $state['ai_openai_model'], $model_id ); ?>><?php echo esc_html( $model_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				</details>

				<details class="importonbridge-accordion" style="margin-bottom:12px;">
					<summary>
						<div class="importonbridge-accordion-copy">
							<span class="importonbridge-accordion-title">Gemini</span>
							<span class="importonbridge-accordion-meta"><?php echo $state['ai_gemini_key_saved'] ? esc_html__( 'Key saved', 'importon-bridge' ) : esc_html__( 'Add key and model', 'importon-bridge' ); ?> · <?php echo esc_html( $state['ai_gemini_model'] ); ?></span>
						</div>
					</summary>
					<div class="importonbridge-accordion-body">
						<div class="importonbridge-kv">
							<div class="importonbridge-k">API Key</div>
							<div class="importonbridge-v importonbridge-inline-control">
								<input type="password" name="importonbridge_ai_gemini_api_key" placeholder="<?php echo $state['ai_gemini_key_saved'] ? 'Gemini key saved - leave blank to keep existing' : 'AIza...'; ?>" autocomplete="new-password" style="flex:1;min-width:200px;">
								<?php if ( $state['ai_gemini_key_saved'] ) : ?>
									<span class="importonbridge-inline-badge importonbridge-inline-badge--success">&#10003; Key saved</span>
								<?php endif; ?>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Model</div>
							<div class="importonbridge-v">
								<?php
								$gemini_models = array(
									'gemini-2.0-flash-exp'        => 'gemini-2.0-flash-exp (recommended)',
									'gemini-2.5-flash-preview-05-20' => 'gemini-2.5-flash-preview-05-20',
									'gemini-2.5-flash'            => 'gemini-2.5-flash (latest stable)',
									'gemini-2.5-pro-preview-05-20'  => 'gemini-2.5-pro-preview-05-20',
									'gemini-1.5-pro'              => 'gemini-1.5-pro',
									'gemini-1.5-flash'            => 'gemini-1.5-flash',
								);
								?>
								<select name="importonbridge_ai_gemini_model">
									<?php foreach ( $gemini_models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $state['ai_gemini_model'], $model_id ); ?>><?php echo esc_html( $model_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				</details>

				<details class="importonbridge-accordion" style="margin-bottom:12px;">
					<summary>
						<div class="importonbridge-accordion-copy">
							<span class="importonbridge-accordion-title">Content Rules</span>
							<span class="importonbridge-accordion-meta">Keywords and CTA defaults for rewritten content.</span>
						</div>
					</summary>
					<div class="importonbridge-accordion-body">
						<div class="importonbridge-kv">
							<div class="importonbridge-k">Global Keywords</div>
							<div class="importonbridge-v">
								<input type="text" name="importonbridge_keywords" value="<?php echo esc_attr( $state['ai_keywords'] ); ?>" placeholder="wholesale, bulk, factory direct">
								<div class="importonbridge-field-help">Comma-separated list of keywords to inject into all rewritten content.</div>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Add Keywords</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_add_keywords" value="1" <?php checked( $state['ai_add_keywords'] ); ?>>
									<span>Prepend keywords to product description</span>
								</label>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">CTA Text</div>
							<div class="importonbridge-v">
								<input type="text" name="importonbridge_cta_url" value="<?php echo esc_attr( $state['ai_cta_url'] ); ?>" placeholder="Call us: +1-234-567-8900">
								<div class="importonbridge-field-help">Call-to-action text appended to rewritten descriptions.</div>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Add CTA</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_add_cta" value="1" <?php checked( $state['ai_add_cta'] ); ?>>
									<span>Append CTA to product description</span>
								</label>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Title Prompt Instructions</div>
							<div class="importonbridge-v">
								<textarea name="importonbridge_title_prompt_instructions" rows="3" placeholder="Extra instructions for AI title output" style="width:100%;"><?php echo esc_textarea( $state['ai_title_prompt_instructions'] ); ?></textarea>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Description Prompt Instructions</div>
							<div class="importonbridge-v">
								<textarea name="importonbridge_description_prompt_instructions" rows="5" placeholder="Extra instructions for AI description output" style="width:100%;"><?php echo esc_textarea( $state['ai_description_prompt_instructions'] ); ?></textarea>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Tag Prompt Instructions</div>
							<div class="importonbridge-v">
								<textarea name="importonbridge_tag_prompt_instructions" rows="4" placeholder="Extra instructions for AI tags output" style="width:100%;"><?php echo esc_textarea( $state['ai_tag_prompt_instructions'] ); ?></textarea>
								<div class="importonbridge-field-help">Used only when Auto Write Tags is enabled and AI returns tags.</div>
							</div>
						</div>
					</div>
				</details>

				<details class="importonbridge-accordion" style="margin-bottom:20px;">
					<summary>
						<div class="importonbridge-accordion-copy">
							<span class="importonbridge-accordion-title">Import Behavior</span>
							<span class="importonbridge-accordion-meta">Control automatic tags and generated SKU format.</span>
						</div>
					</summary>
					<div class="importonbridge-accordion-body">
						<div class="importonbridge-kv">
							<div class="importonbridge-k">Auto Write Tags</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_auto_tags" value="1" <?php checked( $state['ai_auto_tags'] ); ?>>
									<span>Write product tags automatically during import</span>
								</label>
								<div class="importonbridge-field-help">When disabled, the importer leaves product tags untouched.</div>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">Auto SKU Format</div>
							<div class="importonbridge-v">
								<label class="importonbridge-toggle">
									<input type="checkbox" name="importonbridge_auto_sku_format" value="1" <?php checked( $state['ai_auto_sku_format'] ); ?>>
									<span>Generate formatted SKU automatically for imported products</span>
								</label>
								<div class="importonbridge-field-help">When disabled, imported SKU stays manual. Existing SKU is preserved and new products keep the incoming SKU if provided.</div>
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">SKU Prefix</div>
							<div class="importonbridge-v">
								<input type="text" name="importonbridge_sku_prefix" value="<?php echo esc_attr( $state['ai_sku_prefix'] ); ?>" placeholder="F" maxlength="8" style="max-width:120px;">
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">SKU Middle</div>
							<div class="importonbridge-v">
								<input type="text" name="importonbridge_sku_middle_prefix" value="<?php echo esc_attr( $state['ai_sku_middle_prefix'] ); ?>" placeholder="G" maxlength="8" style="max-width:120px;">
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">SKU Suffix</div>
							<div class="importonbridge-v">
								<input type="text" name="importonbridge_sku_suffix" value="<?php echo esc_attr( $state['ai_sku_suffix'] ); ?>" placeholder="K" maxlength="8" style="max-width:120px;">
							</div>
						</div>

						<div class="importonbridge-kv">
							<div class="importonbridge-k">SKU Number Length</div>
							<div class="importonbridge-v">
								<input type="number" name="importonbridge_sku_number_length" value="<?php echo esc_attr( (string) $state['ai_sku_number_length'] ); ?>" min="1" max="8" style="max-width:100px;">
								<div class="importonbridge-field-help">Example format: <?php echo esc_html( $state['ai_sku_prefix'] . '0' . $state['ai_sku_middle_prefix'] . str_repeat( '0', max( 1, (int) $state['ai_sku_number_length'] ) ) . $state['ai_sku_suffix'] ); ?></div>
							</div>
						</div>
					</div>
				</details>

				<div class="importonbridge-actions">
					<div class="importonbridge-btn-row">
						<button type="submit" class="importonbridge-btn" name="importonbridge_save_ai_settings" value="1">Save Settings</button>
						<button type="submit" class="importonbridge-ghost-btn" name="importonbridge_test_openai_api" value="1">Test OpenAI</button>
						<button type="submit" class="importonbridge-ghost-btn" name="importonbridge_test_gemini_api" value="1">Test Gemini</button>
					</div>
					<div class="importonbridge-field-help">The test buttons use the same key and model fields shown above. If you typed a new key but did not save yet, the test still uses that current value for this request.</div>
				</div>
			</form>
		</div>
		<?php
	}

	public static function render_url_import_page(): void {
		self::assert_access();

		$latest_run = ImportonBridge_Url_Import::get_latest_run();
		?>
		<div class="importonbridge-wrap importonbridge-shell importonbridge-page">
			<meta name="importonbridge-url-import-bridge" content="1">

			<div class="importonbridge-hero importonbridge-hero--import">
				<div class="importonbridge-hero-copy">
					<h1>Batch Import from Alibaba</h1>
					<p>Queue product-detail URLs, assign a WooCommerce category once, and let the browser companion run the same import flow with better visibility and retry support.</p>
				</div>
				<div class="importonbridge-hero-side">
					<div class="importonbridge-hero-actions">
						<a class="importonbridge-ghost-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=importon-bridge' ) ); ?>">Settings</a>
					</div>
				</div>
			</div>

			<?php $is_locked = ! self::is_pro_active(); ?>
			<?php if ( $is_locked ) : ?><div class="importonbridge-locked-wrapper importonbridge-locked"><?php endif; ?>
			<div class="importonbridge-grid importonbridge-grid--import">
				<div class="importonbridge-card importonbridge-card--section importonbridge-card--highlight">
					<div class="importonbridge-card-head">
						<div>
							<h2>Import Queue</h2>
							<p>Paste one product-detail URL per line. Duplicate URLs are removed before the run starts.</p>
						</div>
					</div>
					<div class="importonbridge-field">
						<label for="importonbridge-url-import-urls">Product URLs</label>
						<textarea id="importonbridge-url-import-urls" rows="12" placeholder="https://www.alibaba.com/product-detail/demo-product-name_160000000001.html&#10;https://chinaheadwearfactory.com/product/custom-snapback-hat/"></textarea>
						<div class="importonbridge-field-help">One product URL per line. Supports Alibaba and any product page with schema markup.</div>
					</div>

					<div class="importonbridge-form-inline">
						<div class="importonbridge-field">
							<label for="importonbridge-url-import-category">WooCommerce category</label>
							<select id="importonbridge-url-import-category"></select>
						</div>
						<div class="importonbridge-field importonbridge-field-actions">
							<label>&nbsp;</label>
							<div class="importonbridge-btn-row">
								<button type="button" class="importonbridge-btn" id="importonbridge-url-import-start" formnovalidate>Import</button>
								<button type="button" class="importonbridge-ghost-btn" id="importonbridge-url-import-retry" formnovalidate>Retry Failed</button>
							</div>
						</div>
					</div>
				</div>

				<div class="importonbridge-card importonbridge-card--section">
					<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
						<span style="font-size:13px;font-weight:600;color:#475569;">Latest Failed Log</span>
						<a id="importonbridge-url-import-log-link" href="<?php echo ! empty( $latest_run['log_url'] ) ? esc_url( $latest_run['log_url'] ) : '#'; ?>" target="_blank" rel="noopener" style="font-size:12px;color:#2563eb;text-decoration:none;">View Log →</a>
					</div>

					<div class="importonbridge-card-head importonbridge-card-head--compact" style="margin-top:8px;margin-bottom:12px;">
						<div>
							<h2>Live Progress</h2>
							<p style="font-size:12px;color:#64748b;">Real-time import progress</p>
						</div>
					</div>

					<div class="importonbridge-run-stats-grid">
						<div class="importonbridge-stat-box importonbridge-stat-box--status">
							<div class="importonbridge-stat-label">Status</div>
							<div style="font-size:13px;font-weight:600;color:#64748b;display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;" id="importonbridge-run-status"><span style="width:8px;height:8px;background:#94a3b8;border-radius:50%;display:inline-block;flex-shrink:0;"></span>&nbsp;Ready</div>
						</div>
						<div class="importonbridge-stat-box">
							<div class="importonbridge-stat-label">Total</div>
							<div class="importonbridge-stat-value" id="importonbridge-run-total" style="font-size:22px;">0</div>
						</div>
						<div class="importonbridge-stat-box">
							<div class="importonbridge-stat-label">Processed</div>
							<div class="importonbridge-stat-value" id="importonbridge-run-processed" style="font-size:22px;">0</div>
						</div>
						<div class="importonbridge-stat-box">
							<div class="importonbridge-stat-label">Success</div>
							<div class="importonbridge-stat-value" id="importonbridge-run-success" style="font-size:22px;">0</div>
						</div>
						<div class="importonbridge-stat-box">
							<div class="importonbridge-stat-label">Failed</div>
							<div class="importonbridge-stat-value" id="importonbridge-run-failed" style="font-size:22px;">0</div>
						</div>
					</div>

					<div style="margin-bottom:8px;">
						<div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px;">
							<span>Progress</span>
							<span id="importonbridge-run-processed-label">0%</span>
						</div>
						<div style="width:100%;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
							<div class="importonbridge-progress-bar" id="importonbridge-run-progress-bar" style="width:0%;height:100%;background:linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);border-radius:4px;transition:width 0.3s ease;"></div>
						</div>
					</div>

					<p id="importonbridge-run-message" style="font-size:12px;color:#64748b;margin-top:12px;text-align:center;font-style:italic;">No run started yet. Add URLs and click Import to begin.</p>
				</div>
			</div>

			<div class="importonbridge-card importonbridge-card--section importonbridge-card--recent">
				<div class="importonbridge-card-head">
					<div>
						<h2>Recent Runs</h2>
						<p style="font-size:12px;color:#64748b;margin-top:4px;">Track your import history and results</p>
					</div>
					<button type="button" class="importonbridge-ghost-btn" id="importonbridge-url-import-clear-runs">Clear All</button>
				</div>
				<div class="importonbridge-runs-grid" id="importonbridge-runs-grid">
					<div class="importonbridge-runs-header">
						<span>Run ID</span>
						<span>Category</span>
						<span>Status</span>
						<span>Total</span>
						<span>Success</span>
						<span>Failed</span>
						<span>Log</span>
					</div>
					<div class="importonbridge-runs-body" id="importonbridge-url-import-runs-body">
						<div class="importonbridge-empty-state">No import runs yet.</div>
					</div>
				</div>
			</div>

			<?php if ( $is_locked ) : ?>
			<div class="importonbridge-premium-overlay">
				<div class="importonbridge-premium-card">
					<div class="importonbridge-premium-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> PREMIUM FEATURE</div>
					<div class="importonbridge-premium-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1"/></svg></div>
					<h3>Unlock <span>Batch URL Import</span></h3>
					<p>Queue unlimited Alibaba product URLs, run them with retry & logs — built for serious dropshippers. Upgrade to Pro to unlock this workspace.</p>
					<div class="importonbridge-premium-features">
						<div class="importonbridge-premium-feature"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Unlimited URL queue</div>
						<div class="importonbridge-premium-feature"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Batch & retry engine</div>
						<div class="importonbridge-premium-feature"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Failed log & history</div>
						<div class="importonbridge-premium-feature"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Priority support</div>
					</div>
					<a class="importonbridge-premium-cta" href="<?php echo esc_url( function_exists('ib_fs') ? ib_fs()->get_upgrade_url() : 'https://checkout.freemius.com/product/28475/plan/46909/' ); ?>" target="_blank" rel="noopener">View Plans & Start Trial →</a>
					<div class="importonbridge-premium-note">14-day money-back guarantee · Freemius secure checkout</div>
				</div>
			</div>
			</div>
			<?php endif; ?>

		<?php
	}

	private static function assert_access(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'importon-bridge' ) );
		}
	}

	private static function can_manage(): bool {
		$cap = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
		return current_user_can( $cap );
	}

	private static function is_pro_active(): bool {
		if ( function_exists( 'ib_fs' ) ) {
			if ( is_callable( array( ib_fs(), 'is_trial' ) ) && ib_fs()->is_trial() ) {
				return true;
			}
			if ( is_callable( array( ib_fs(), 'is_paying' ) ) && ib_fs()->is_paying() ) {
				return true;
			}
			if ( is_callable( array( ib_fs(), 'can_use_premium_code__premium_only' ) ) && ib_fs()->can_use_premium_code__premium_only() ) {
				return (bool) ib_fs()->can_use_premium_code__premium_only();
			}
			if ( is_callable( array( ib_fs(), 'can_use_premium_code' ) ) && ib_fs()->can_use_premium_code() ) {
				return (bool) ib_fs()->can_use_premium_code();
			}
		}
		// Backward compat if old atwi_fs still present
		if ( function_exists( 'atwi_fs' ) && is_callable( array( atwi_fs(), 'can_use_premium_code' ) ) ) {
			return (bool) atwi_fs()->can_use_premium_code();
		}
		return (bool) get_option( 'importonbridge_pro_unlocked', false );
	}

	private static function build_ai_settings_from_post( array $current, array $post ): array {
		$new_openai_key = isset( $post['importonbridge_ai_openai_api_key'] ) ? sanitize_text_field( (string) $post['importonbridge_ai_openai_api_key'] ) : '';
		if ( $new_openai_key !== '' ) {
			$current['openai_api_key'] = $new_openai_key;
			$current['api_key']        = $new_openai_key;
		}

		$new_gemini_key = isset( $post['importonbridge_ai_gemini_api_key'] ) ? sanitize_text_field( (string) $post['importonbridge_ai_gemini_api_key'] ) : '';
		if ( $new_gemini_key !== '' ) {
			$current['gemini_api_key'] = $new_gemini_key;
		}

		// Text field holds the real model ID (JS updates it when preset selected, user types when custom).
		$openai_model = isset( $post['importonbridge_ai_openai_model'] ) ? sanitize_text_field( (string) $post['importonbridge_ai_openai_model'] ) : '';
		if ( $openai_model === '' || $openai_model === 'custom' ) {
			$openai_model = isset( $post['importonbridge_ai_openai_model_select'] ) ? sanitize_text_field( (string) $post['importonbridge_ai_openai_model_select'] ) : '';
		}
		if ( $openai_model === '' || $openai_model === 'custom' ) {
			$openai_model = 'gpt-4o-mini';
		}
		$gemini_model = isset( $post['importonbridge_ai_gemini_model'] ) ? sanitize_text_field( (string) $post['importonbridge_ai_gemini_model'] ) : '';
		if ( $gemini_model === '' || $gemini_model === 'custom' ) {
			$gemini_model = isset( $post['importonbridge_ai_gemini_model_select'] ) ? sanitize_text_field( (string) $post['importonbridge_ai_gemini_model_select'] ) : '';
		}
		if ( $gemini_model === '' || $gemini_model === 'custom' ) {
			$gemini_model = 'gemini-2.5-flash';
		}
		$current['openai_model'] = $openai_model;
		$current['gemini_model'] = $gemini_model;

		$provider_order = isset( $post['importonbridge_ai_provider_order'] ) ? sanitize_key( (string) $post['importonbridge_ai_provider_order'] ) : 'openai_first';
		if ( ! in_array( $provider_order, array( 'openai_first', 'gemini_first' ), true ) ) {
			$provider_order = 'openai_first';
		}

		$current['enabled']        = ! empty( $post['importonbridge_ai_enabled'] );
		$current['rewrite_title']  = ! empty( $post['importonbridge_rewrite_title'] );
		$current['rewrite_description'] = ! empty( $post['importonbridge_rewrite_description'] );
		$current['cta_url']        = isset( $post['importonbridge_cta_url'] ) ? esc_url_raw( (string) $post['importonbridge_cta_url'] ) : '';
		$current['keywords']       = isset( $post['importonbridge_keywords'] ) ? sanitize_text_field( (string) $post['importonbridge_keywords'] ) : '';
		$current['title_prompt_instructions'] = isset( $post['importonbridge_title_prompt_instructions'] ) ? sanitize_textarea_field( (string) $post['importonbridge_title_prompt_instructions'] ) : '';
		$current['description_prompt_instructions'] = isset( $post['importonbridge_description_prompt_instructions'] ) ? sanitize_textarea_field( (string) $post['importonbridge_description_prompt_instructions'] ) : '';
		$current['tag_prompt_instructions'] = isset( $post['importonbridge_tag_prompt_instructions'] ) ? sanitize_textarea_field( (string) $post['importonbridge_tag_prompt_instructions'] ) : '';
		$current['provider_order'] = $provider_order;
		$current['auto_tags']      = ! empty( $post['importonbridge_auto_tags'] );
		$current['auto_sku_format'] = ! empty( $post['importonbridge_auto_sku_format'] );
		$current['sku_prefix']      = self::sanitize_sku_format_part( isset( $post['importonbridge_sku_prefix'] ) ? (string) $post['importonbridge_sku_prefix'] : 'F', 'F' );
		$current['sku_middle_prefix'] = self::sanitize_sku_format_part( isset( $post['importonbridge_sku_middle_prefix'] ) ? (string) $post['importonbridge_sku_middle_prefix'] : 'G', 'G' );
		$current['sku_suffix']        = self::sanitize_sku_format_part( isset( $post['importonbridge_sku_suffix'] ) ? (string) $post['importonbridge_sku_suffix'] : 'K', 'K' );
		$current['sku_number_length'] = self::sanitize_sku_number_length( isset( $post['importonbridge_sku_number_length'] ) ? $post['importonbridge_sku_number_length'] : 3 );

		return $current;
	}

	private static function sanitize_sku_format_part( string $value, string $fallback ): string {
		$value = strtoupper( trim( $value ) );
		$value = preg_replace( '/[^A-Z0-9_-]/', '', $value );
		if ( ! is_string( $value ) || $value === '' ) {
			return $fallback;
		}
		return substr( $value, 0, 8 );
	}

	private static function sanitize_sku_number_length( $value ): int {
		$length = (int) $value;
		if ( $length < 1 ) {
			$length = 1;
		}
		if ( $length > 8 ) {
			$length = 8;
		}
		return $length;
	}

	private static function handle_settings_postback(): array {
		$ai_notice = '';
		$ai_error  = '';
		$ai_settings = get_option( 'importonbridge_ai_settings', array() );
		if ( ! is_array( $ai_settings ) ) {
			$ai_settings = array();
		}

		if ( isset( $_POST['importonbridge_save_ai_settings'] ) || isset( $_POST['importonbridge_test_openai_api'] ) || isset( $_POST['importonbridge_test_gemini_api'] ) ) {
			check_admin_referer( 'importonbridge_save_ai_settings_action', 'importonbridge_save_ai_settings_nonce' );

			$ai_settings = self::build_ai_settings_from_post( $ai_settings, wp_unslash( $_POST ) );

			if ( isset( $_POST['importonbridge_save_ai_settings'] ) ) {
				update_option( 'importonbridge_ai_settings', $ai_settings );
				$ai_notice = __( 'AI settings saved.', 'importon-bridge' );
			}

			if ( isset( $_POST['importonbridge_test_openai_api'] ) || isset( $_POST['importonbridge_test_gemini_api'] ) ) {
				$provider    = isset( $_POST['importonbridge_test_gemini_api'] ) ? 'gemini' : 'openai';
				$test_result = ImportonBridge_Rest::test_ai_provider_connection( $ai_settings, $provider );
				if ( ! empty( $test_result['ok'] ) ) {
					$ai_notice = isset( $test_result['message'] ) ? (string) $test_result['message'] : sprintf( __( '%s connection succeeded.', 'importon-bridge' ), ucfirst( $provider ) );
				} else {
					$ai_error = isset( $test_result['message'] ) ? (string) $test_result['message'] : sprintf( __( '%s connection failed.', 'importon-bridge' ), ucfirst( $provider ) );
				}
			}
		}

		$ai_enabled            = ! isset( $ai_settings['enabled'] ) ? true : ! empty( $ai_settings['enabled'] );
		$ai_rewrite_title      = ! isset( $ai_settings['rewrite_title'] ) ? true : ! empty( $ai_settings['rewrite_title'] );
		$ai_rewrite_description = ! isset( $ai_settings['rewrite_description'] ) ? true : ! empty( $ai_settings['rewrite_description'] );
		$ai_openai_key_saved   = ! empty( $ai_settings['openai_api_key'] ) || ! empty( $ai_settings['api_key'] );
		$ai_gemini_key_saved   = ! empty( $ai_settings['gemini_api_key'] );
		$ai_cta_url            = isset( $ai_settings['cta_url'] ) ? (string) $ai_settings['cta_url'] : '';
		$ai_keywords           = isset( $ai_settings['keywords'] ) ? (string) $ai_settings['keywords'] : '';
		$ai_title_prompt_instructions = isset( $ai_settings['title_prompt_instructions'] ) ? (string) $ai_settings['title_prompt_instructions'] : '';
		$ai_description_prompt_instructions = isset( $ai_settings['description_prompt_instructions'] ) ? (string) $ai_settings['description_prompt_instructions'] : '';
		$ai_tag_prompt_instructions = isset( $ai_settings['tag_prompt_instructions'] ) ? (string) $ai_settings['tag_prompt_instructions'] : '';
		$ai_provider_order     = isset( $ai_settings['provider_order'] ) && in_array( $ai_settings['provider_order'], array( 'openai_first', 'gemini_first' ), true ) ? (string) $ai_settings['provider_order'] : 'openai_first';
		$ai_openai_model       = isset( $ai_settings['openai_model'] ) && is_string( $ai_settings['openai_model'] ) && $ai_settings['openai_model'] !== '' ? (string) $ai_settings['openai_model'] : 'gpt-4o';
		$ai_gemini_model       = isset( $ai_settings['gemini_model'] ) && is_string( $ai_settings['gemini_model'] ) && $ai_settings['gemini_model'] !== '' ? (string) $ai_settings['gemini_model'] : 'gemini-2.5-flash';
		$ai_auto_tags          = ! empty( $ai_settings['auto_tags'] );
		$ai_auto_sku_format    = ! empty( $ai_settings['auto_sku_format'] );
		$ai_sku_prefix         = self::sanitize_sku_format_part( isset( $ai_settings['sku_prefix'] ) ? (string) $ai_settings['sku_prefix'] : 'F', 'F' );
		$ai_sku_middle_prefix  = self::sanitize_sku_format_part( isset( $ai_settings['sku_middle_prefix'] ) ? (string) $ai_settings['sku_middle_prefix'] : 'G', 'G' );
		$ai_sku_suffix         = self::sanitize_sku_format_part( isset( $ai_settings['sku_suffix'] ) ? (string) $ai_settings['sku_suffix'] : 'K', 'K' );
		$ai_sku_number_length  = self::sanitize_sku_number_length( isset( $ai_settings['sku_number_length'] ) ? $ai_settings['sku_number_length'] : 3 );

		return array(
			'ai_notice'           => $ai_notice,
			'ai_error'            => $ai_error,
			'ai_enabled'          => $ai_enabled,
			'ai_rewrite_title'    => $ai_rewrite_title,
			'ai_rewrite_description' => $ai_rewrite_description,
			'ai_openai_key_saved' => $ai_openai_key_saved,
			'ai_gemini_key_saved' => $ai_gemini_key_saved,
			'ai_provider_order'   => $ai_provider_order,
			'ai_openai_model'     => $ai_openai_model,
			'ai_gemini_model'     => $ai_gemini_model,
			'ai_cta_url'          => $ai_cta_url,
			'ai_keywords'         => $ai_keywords,
			'ai_title_prompt_instructions' => $ai_title_prompt_instructions,
			'ai_description_prompt_instructions' => $ai_description_prompt_instructions,
			'ai_tag_prompt_instructions' => $ai_tag_prompt_instructions,
			'ai_auto_tags'        => $ai_auto_tags,
			'ai_auto_sku_format'  => $ai_auto_sku_format,
			'ai_sku_prefix'       => $ai_sku_prefix,
			'ai_sku_middle_prefix'=> $ai_sku_middle_prefix,
			'ai_sku_suffix'       => $ai_sku_suffix,
			'ai_sku_number_length'=> $ai_sku_number_length,
		);
	}

	private static function get_common_admin_css(): string {
		$mono = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif";
		return implode(
			"\n",
			array(
				/* ── Custom scrollbar ──────────────────────────────────────────── */
				'.importonbridge-shell ::-webkit-scrollbar { width: 4px; height: 4px; }',
				'.importonbridge-shell ::-webkit-scrollbar-track { background: transparent; }',
				'.importonbridge-shell ::-webkit-scrollbar-thumb { background: #c0c0c4; border-radius: 3px; }',
				'.importonbridge-shell ::-webkit-scrollbar-thumb:hover { background: #86868b; }',
				'.importonbridge-shell { scrollbar-width: thin; scrollbar-color: #c0c0c4 transparent; }',
				/* ── Base ─────────────────────────────────────────────────────── */
				'.importonbridge-shell { --bg: #f5f5f7; --card: #fff; --text: #1d1d1f; --text-dim: #86868b; --text-faint: #c0c0c4; --border: #e0e0e0; --border-strong: #ccc; --accent: #0071e3; --mono: ' . $mono . '; width: 100%; max-width: 100%; margin: 0; color: var(--text); font-family: var(--mono); }',
				'.importonbridge-wrap { width: 100%; max-width: 1200px; margin: 20px auto; clear: both; overflow: visible; padding-bottom: 120px; }',
				'.importonbridge-shell *, .importonbridge-shell *::before, .importonbridge-shell *::after { box-sizing: border-box; }',
				'.importonbridge-shell a { color: var(--text); text-decoration: underline; }',
				'.importonbridge-shell a:hover { color: #000; }',
				'.importonbridge-wrap.importonbridge-shell { padding-right: 16px; }',
				/* ── Hero ─────────────────────────────────────────────────────── */
				'.importonbridge-hero { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: center; margin-bottom: 20px; padding: 24px 28px; background: #fff; border: 1px solid var(--border); border-radius: 8px; position: relative; }',
				'.importonbridge-hero::before { content: ""; position: absolute; top: 3px; left: 3px; width: 8px; height: 8px; border-top: 1px solid var(--text-faint); border-left: 1px solid var(--text-faint); pointer-events: none; }',
				'.importonbridge-hero::after { content: ""; position: absolute; bottom: 3px; right: 3px; width: 8px; height: 8px; border-bottom: 1px solid var(--text-faint); border-right: 1px solid var(--text-faint); pointer-events: none; }',
				'.importonbridge-hero-copy h1 { margin: 0; font-size: 20px; font-weight: 700; color: var(--text); letter-spacing: -0.02em; }',
				'.importonbridge-hero-copy p { margin: 8px 0 0; color: var(--text-dim); font-size: 12px; max-width: 600px; line-height: 1.5; }',
				'.importonbridge-hero-side { display: flex; gap: 8px; flex-wrap: wrap; }',
				'.importonbridge-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }',
				/* ── Ghost download button ─────────────────────────────────────── */
				'.importonbridge-btn-download { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 4px; font-weight: 500; font-size: 12px; cursor: pointer; text-decoration: none; border: 1px solid var(--border); background: var(--card); color: var(--text); font-family: var(--mono); }',
				'.importonbridge-btn-download:hover { border-color: var(--text-dim); }',
				/* ── Grids ─────────────────────────────────────────────────────── */
				'.importonbridge-overview-grid, .importonbridge-panel-grid, .importonbridge-grid { display: grid; gap: 16px; }',
				'.importonbridge-overview-grid { grid-template-columns: repeat(2, 1fr); margin-bottom: 16px; }',
				'.importonbridge-panel-grid { grid-template-columns: repeat(2, 1fr); margin-bottom: 16px; }',
				'.importonbridge-grid--import { grid-template-columns: 1fr 340px; margin-bottom: 16px; }',
				'.importonbridge-grid--tables { grid-template-columns: repeat(2, 1fr); }',
				/* ── Cards ─────────────────────────────────────────────────────── */
				'.importonbridge-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 20px; }',
				'.importonbridge-card--soft { background: var(--bg); }',
				'.importonbridge-card--highlight { background: var(--card); }',
				'.importonbridge-card--cta { margin-top: 0; }',
				'.importonbridge-card--recent { margin-top: 16px; padding: 0; }',
				'.importonbridge-card--recent .importonbridge-card-head { padding: 16px 16px 0; }',
				'.importonbridge-card--recent .importonbridge-runs-grid { border: none; border-radius: 0; }',
				'.importonbridge-card-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 16px; }',
				'.importonbridge-card-head--compact { margin-bottom: 12px; }',
				'.importonbridge-card-head--top-gap { margin-top: 20px; }',
				'.importonbridge-card-head h2, .importonbridge-card-head h3 { margin: 0; color: var(--text); font-family: var(--mono); }',
				'.importonbridge-card-head h2 { font-size: 14px; font-weight: 700; letter-spacing: -0.01em; }',
				'.importonbridge-card-head h3 { font-size: 12px; font-weight: 700; }',
				'.importonbridge-card-head p { margin: 4px 0 0; color: var(--text-dim); font-size: 11px; }',
				/* ── Checklist ─────────────────────────────────────────────────── */
				'.importonbridge-checklist { display: grid; gap: 10px; }',
				'.importonbridge-checklist-item { display: grid; grid-template-columns: 24px 1fr; gap: 10px; align-items: start; color: var(--text); font-size: 12px; }',
				'.importonbridge-checkmark { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--bg); color: var(--text-dim); font-size: 11px; font-weight: 600; }',
				/* ── Info Grid ─────────────────────────────────────────────────── */
				'.importonbridge-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }',
				'.importonbridge-info-grid--compact { grid-template-columns: 1fr; }',
				'.importonbridge-info-item { padding: 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--card); min-width: 0; }',
				'.importonbridge-info-item code { display: inline-block; max-width: 100%; overflow-wrap: anywhere; user-select: all; font-size: 11px; font-family: var(--mono); }',
				'.importonbridge-info-label { display: block; margin-bottom: 4px; color: var(--text-dim); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.15em; }',
				/* ── Alerts ────────────────────────────────────────────────────── */
				'.importonbridge-alert { margin-bottom: 14px; padding: 12px 14px; border-radius: 4px; border: 1px solid; font-size: 12px; font-family: var(--mono); }',
				'.importonbridge-alert--success { color: var(--text); background: var(--bg); border-color: var(--border); }',
				'.importonbridge-alert--danger { color: var(--text); background: var(--bg); border-color: var(--border); }',
				/* ── Forms ─────────────────────────────────────────────────────── */
				'.importonbridge-form-stack { display: grid; gap: 16px; }',
				'.importonbridge-ai-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); overflow: hidden; }',
				'.importonbridge-ai-summary-item { min-width: 0; padding: 12px 16px; border-right: 1px solid var(--border); }',
				'.importonbridge-ai-summary-item:last-child { border-right: 0; }',
				'.importonbridge-ai-summary-label { display: block; margin-bottom: 4px; color: var(--text-dim); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.14em; }',
				'.importonbridge-ai-summary-value { display: block; color: var(--text); font-size: 13px; line-height: 1.4; }',
				/* ── Accordion ─────────────────────────────────────────────────── */
				'.importonbridge-accordion { border: 1px solid var(--border); border-radius: 8px; background: var(--card); overflow: hidden; }',
				'.importonbridge-accordion summary { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 16px; cursor: pointer; list-style: none; font-size: 12px; font-weight: 600; font-family: var(--mono); }',
				'.importonbridge-accordion summary::-webkit-details-marker { display: none; }',
				'.importonbridge-accordion summary::after { content: "+"; display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--bg); color: var(--text-dim); font-size: 13px; font-weight: 600; flex: 0 0 auto; }',
				'.importonbridge-accordion[open] summary::after { content: "−"; }',
				'.importonbridge-accordion-copy { display: grid; gap: 4px; min-width: 0; }',
				'.importonbridge-accordion-title { color: var(--text); font-size: 12px; font-weight: 700; }',
				'.importonbridge-accordion-meta { color: var(--text-dim); font-size: 10px; line-height: 1.4; }',
				'.importonbridge-accordion-body { padding: 0 16px 16px; border-top: 1px solid var(--border); background: var(--card); }',
				'.importonbridge-accordion-body .importonbridge-kv:first-child { padding-top: 16px; }',
				'.importonbridge-kv { display: grid; grid-template-columns: 180px 1fr; gap: 12px 16px; align-items: start; }',
				'.importonbridge-k { color: var(--text); font-weight: 500; padding-top: 8px; font-size: 12px; }',
				'.importonbridge-v { min-width: 0; }',
				'.importonbridge-v code { user-select: all; font-family: var(--mono); }',
				/* ── Inline controls ───────────────────────────────────────────── */
				'.importonbridge-inline-control { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }',
				'.importonbridge-inline-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 500; background: var(--bg); color: var(--text); font-family: var(--mono); }',
				'.importonbridge-inline-badge--success { background: var(--bg); color: var(--text); }',
				/* ── Form inputs ────────────────────────────────────────────────── */
				'.importonbridge-form { display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: end; }',
				'.importonbridge-form--password .importonbridge-form-action { display: flex; align-items: flex-end; height: 100%; }',
				'.importonbridge-inline-form { margin: 0; }',
				'.importonbridge-form label, .importonbridge-field label { display: block; margin-bottom: 6px; color: var(--text); font-weight: 500; font-size: 11px; font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.08em; }',
				'.importonbridge-form input[type="text"], .importonbridge-form input[type="url"], .importonbridge-form input[type="password"], .importonbridge-field input[type="text"], .importonbridge-field input[type="url"], .importonbridge-field textarea, .importonbridge-field select, .importonbridge-v input[type="text"], .importonbridge-v input[type="url"], .importonbridge-v input[type="password"] { width: 100%; min-height: 40px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); color: var(--text); font-size: 12px; font-family: var(--mono); }',
				'.importonbridge-form input:focus, .importonbridge-field input:focus, .importonbridge-field textarea:focus, .importonbridge-field select:focus, .importonbridge-v input:focus { border-color: var(--text); outline: none; }',
				'.importonbridge-field textarea, .importonbridge-v textarea { width: 100%; min-height: 80px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); color: var(--text); font-size: 12px; font-family: var(--mono); line-height: 1.5; resize: vertical; box-sizing: border-box; }',
				'.importonbridge-field textarea:focus, .importonbridge-v textarea:focus { border-color: var(--text); outline: none; }',
				'.importonbridge-field select { appearance: auto; -webkit-appearance: auto; }',
				'.importonbridge-field-help { margin-top: 6px; color: var(--text-dim); font-size: 10px; }',
				/* ── Toggle ────────────────────────────────────────────────────── */
				'.importonbridge-toggle { display: inline-flex; align-items: center; gap: 10px; font-weight: 500; font-size: 12px; font-family: var(--mono); }',
				'.importonbridge-toggle input { margin: 0; }',
				/* ── Buttons ───────────────────────────────────────────────────── */
				'.importonbridge-actions { display: grid; gap: 8px; }',
				'.importonbridge-btn, .importonbridge-copy, .importonbridge-ghost-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 10px 16px; border-radius: 4px; font-weight: 700; cursor: pointer; text-decoration: none; font-family: var(--mono); font-size: 11px; letter-spacing: 0.03em; transition: border-color .15s ease, background .15s ease, color .15s ease; }',
				'.importonbridge-btn, .importonbridge-btn:visited, .importonbridge-btn:hover, .importonbridge-btn:focus { color: #fff !important; }',
				'.importonbridge-btn { border: 1px solid var(--text); background: var(--text); }',
				'.importonbridge-btn:hover { background: #000; border-color: #000; }',
				'.importonbridge-btn[disabled] { opacity: .45; cursor: not-allowed; background: var(--text-faint); border-color: var(--text-faint); }',
				'.importonbridge-copy, .importonbridge-ghost-btn { border: 1px solid var(--border); background: var(--card); color: var(--text); }',
				'.importonbridge-copy:hover, .importonbridge-ghost-btn:hover { border-color: var(--text); background: var(--bg); }',
				/* ── Help tooltip ──────────────────────────────────────────────── */
				'.importonbridge-help-wrap { position: relative; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }',
				'.importonbridge-help-trigger { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; border: 1px solid var(--border); background: var(--bg); color: var(--text-dim); font-size: 11px; font-weight: 600; cursor: help; font-family: var(--mono); }',
				'.importonbridge-help-trigger:focus { outline: none; box-shadow: 0 0 0 2px var(--border); }',
				'.importonbridge-help-tooltip { position: absolute; top: calc(100% + 8px); right: 0; width: min(300px, 70vw); padding: 10px 12px; border-radius: 4px; background: var(--text); color: #fff; font-size: 11px; font-family: var(--mono); line-height: 1.5; box-shadow: 0 4px 12px rgba(0,0,0,.15); opacity: 0; visibility: hidden; transform: translateY(-4px); transition: all .15s ease; z-index: 20; }',
				'.importonbridge-help-tooltip::before { content: ""; position: absolute; top: -5px; right: 10px; width: 10px; height: 10px; background: var(--text); transform: rotate(45deg); }',
				'.importonbridge-help-wrap:hover .importonbridge-help-tooltip, .importonbridge-help-wrap:focus-within .importonbridge-help-tooltip { opacity: 1; visibility: visible; transform: translateY(0); }',
				/* ── Password display ──────────────────────────────────────────── */
				'.importonbridge-pass { margin-top: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); }',
				'.importonbridge-pass-title { margin-bottom: 8px; font-weight: 600; color: var(--text); font-size: 12px; }',
				'.importonbridge-pass-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }',
				'.importonbridge-pass code { display: inline-block; padding: 8px 10px; border-radius: 4px; background: var(--card); border: 1px solid var(--border); font-size: 11px; font-family: var(--mono); overflow-wrap: anywhere; }',
				'.importonbridge-subsection { margin-top: 16px; }',
				'.importonbridge-form-inline { display: grid; grid-template-columns: 1fr 200px; gap: 12px; align-items: end; margin-top: 12px; }',
				'.importonbridge-field-actions { min-width: 0; }',
				'.importonbridge-btn-row { display: flex; gap: 8px; flex-wrap: wrap; }',
				/* ── Note box ──────────────────────────────────────────────────── */
				'.importonbridge-note-box { margin-top: 0; padding: 12px; border-radius: 4px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 12px; font-family: var(--mono); }',
				'.importonbridge-note-box--status { display: grid; gap: 6px; }',
				'.importonbridge-note-box--hidden { display: none; }',
				'.importonbridge-note-box--status[data-tone="success"] { background: var(--bg); border-color: var(--border); }',
				'.importonbridge-note-box--status[data-tone="warning"] { background: var(--bg); border-color: var(--border); }',
				'.importonbridge-note-box--status[data-tone="danger"] { background: var(--bg); border-color: var(--border); }',
				/* ── Status pills ──────────────────────────────────────────────── */
				'.importonbridge-status-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 500; letter-spacing: 0.05em; background: var(--bg); color: var(--text); font-family: var(--mono); }',
				'.importonbridge-status-pill--neutral { background: var(--bg); color: var(--text); }',
				'.importonbridge-status-pill--success { background: var(--bg); color: var(--text); }',
				'.importonbridge-status-pill--warning { background: var(--bg); color: var(--text); }',
				'.importonbridge-status-pill--danger { background: var(--bg); color: var(--text); }',
				/* ── Stats ─────────────────────────────────────────────────────── */
				'.importonbridge-run-overview { display: grid; gap: 12px; }',
				'.importonbridge-run-status-card { display: grid; gap: 10px; padding: 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); }',
				'.importonbridge-run-status-meta { display: grid; gap: 6px; }',
				'.importonbridge-run-status-value { font-size: 26px; line-height: 1.1; font-weight: 700; color: var(--text); word-break: break-word; }',
				'.importonbridge-run-status-text { margin: 0; color: var(--text-dim); line-height: 1.4; font-size: 12px; }',
				'.importonbridge-run-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; }',
				'.importonbridge-run-stats-grid .importonbridge-stat-box--status { grid-column: 1 / -1; }',
				'.importonbridge-stat { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 14px; }',
				'.importonbridge-stat--compact .importonbridge-stat-value { font-size: 20px; }',
				'.importonbridge-stat-label { color: var(--text-dim); font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.14em; font-family: var(--mono); }',
				'.importonbridge-stat-value { margin-top: 6px; font-size: 24px; line-height: 1; font-weight: 700; color: var(--text); }',
				/* ── Stats boxes (usage page) ──────────────────────────────────── */
				'.importonbridge-stat-box { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center; }',
				'.importonbridge-stat-box .importonbridge-stat-label { margin-bottom: 8px; }',
				'.importonbridge-stat-box .importonbridge-stat-value { font-size: 26px; margin-top: 0; }',
				'.importonbridge-stats-row { display: grid; gap: 16px; margin-bottom: 20px; }',
				/* ── Progress ──────────────────────────────────────────────────── */
				'.importonbridge-progress { margin-top: 12px; width: 100%; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }',
				'.importonbridge-progress-bar { width: 0; height: 100%; background: var(--text); transition: width .2s ease; }',
				'.importonbridge-muted { color: var(--text-dim); }',
				'.importonbridge-empty-state { color: var(--text-dim); text-align: center; padding: 16px; font-size: 11px; font-family: var(--mono); }',
				/* ── Tables ────────────────────────────────────────────────────── */
				'.importonbridge-table-wrap { width: 100%; overflow: hidden; }',
				'.importonbridge-table-wrap--recent { overflow: visible; }',
				'.importonbridge-table { width: 100%; min-width: 0; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }',
				'.importonbridge-table thead th { background: var(--bg); color: var(--text-dim); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.14em; padding: 12px; text-align: left; font-family: var(--mono); }',
				'.importonbridge-table td, .importonbridge-table th { padding: 10px 12px; border-bottom: 1px solid var(--bg); font-size: 12px; }',
				'.importonbridge-table tr:last-child td { border-bottom: none; }',
				'.importonbridge-run-table { table-layout: fixed; }',
				'.importonbridge-run-table td, .importonbridge-run-table th { overflow-wrap: anywhere; word-break: break-word; }',
				'.importonbridge-failed-table td, .importonbridge-run-table td { vertical-align: top; }',
				/* ── Runs grid ─────────────────────────────────────────────────── */
				'.importonbridge-runs-grid { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--card); }',
				'.importonbridge-runs-header { display: grid; grid-template-columns: 1.5fr 1fr 100px 70px 70px 70px 80px; gap: 8px; padding: 12px 16px; background: var(--bg); border-bottom: 1px solid var(--border); font-size: 9px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.14em; font-family: var(--mono); }',
				'.importonbridge-runs-body { max-height: 300px; overflow-y: auto; }',
				'.importonbridge-run-row { display: grid; grid-template-columns: 1.5fr 1fr 100px 70px 70px 70px 80px; gap: 8px; padding: 14px 16px; align-items: center; border-bottom: 1px solid var(--bg); transition: background 0.15s ease; }',
				'.importonbridge-run-row:hover { background: var(--bg); }',
				'.importonbridge-run-row:last-child { border-bottom: none; }',
				'.importonbridge-run-col { font-size: 12px; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }',
				'.importonbridge-run-id { font-size: 10px; color: var(--text-dim); font-family: var(--mono); }',
				'.importonbridge-run-category { color: var(--text); }',
				'.importonbridge-run-total, .importonbridge-run-success, .importonbridge-run-failed { text-align: center; font-weight: 600; }',
				'.importonbridge-log-link { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 600; color: var(--accent); background: var(--bg); text-decoration: none; font-family: var(--mono); transition: all 0.15s ease; }',
				'.importonbridge-log-link:hover { background: var(--accent); color: #fff; }',
				'.importonbridge-status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--mono); }',
				'.importonbridge-status-success { background: var(--bg); color: var(--text); border: 1px solid var(--border); }',
				'.importonbridge-status-danger { background: var(--bg); color: var(--text); border: 1px solid var(--border); }',
				'.importonbridge-status-running { background: var(--bg); color: var(--text); border: 1px solid var(--border); }',
				'.importonbridge-status-pending { background: var(--bg); color: var(--text-dim); border: 1px solid var(--border); }',
				/* ── Runs responsive ─────────────────────────────────────────── */
				'@media (max-width: 900px) { .importonbridge-runs-header, .importonbridge-run-row { grid-template-columns: 1fr 1fr 80px 60px 80px; } .importonbridge-run-category, .importonbridge-run-total, .importonbridge-run-log { display: none; } }',
				'@media (max-width: 600px) { .importonbridge-runs-grid { border-radius: 6px; } .importonbridge-runs-header { display: none; } .importonbridge-run-row { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px; } .importonbridge-run-col { font-size: 11px; } .importonbridge-run-id { width: 100%; font-size: 9px; } }',
				/* ── Responsive general ─────────────────────────────────────────── */
				'@media (min-width: 1200px) { .importonbridge-grid--import { grid-template-columns: 1fr 360px; } }',
				'@media (max-width: 1080px) { .importonbridge-overview-grid, .importonbridge-panel-grid, .importonbridge-grid--tables, .importonbridge-grid--import, .importonbridge-hero, .importonbridge-ai-summary, .importonbridge-field-grid { grid-template-columns: 1fr; } .importonbridge-ai-summary { grid-template-columns: repeat(2, 1fr); } .importonbridge-ai-summary-item { border-right: 0; border-bottom: 1px solid var(--border); } .importonbridge-ai-summary-item:nth-last-child(-n+2) { border-bottom: 0; } .importonbridge-hero--settings .importonbridge-hero-side { justify-content: flex-start; } .importonbridge-hero-actions { justify-content: flex-start; } .importonbridge-grid--import { grid-template-columns: 1fr; } }',
				'@media (max-width: 782px) { .importonbridge-wrap { margin: 12px auto; padding-bottom: 100px; } .importonbridge-wrap.importonbridge-shell { padding-right: 8px; padding-left: 8px; } .importonbridge-card { padding: 16px; } .importonbridge-hero { padding: 16px; border-radius: 8px; grid-template-columns: 1fr; gap: 12px; } .importonbridge-hero-copy h1 { font-size: 16px; } .importonbridge-hero-copy p { font-size: 11px; } .importonbridge-hero-side { width: 100%; } .importonbridge-hero-side .importonbridge-btn { width: 100%; justify-content: center; } .importonbridge-kv, .importonbridge-form, .importonbridge-form-inline, .importonbridge-info-grid, .importonbridge-field-grid { grid-template-columns: 1fr; } .importonbridge-k { padding-top: 0; font-size: 12px; } .importonbridge-btn, .importonbridge-copy, .importonbridge-ghost-btn { width: 100%; justify-content: center; } .importonbridge-btn-row { display: grid; grid-template-columns: 1fr; gap: 8px; } .importonbridge-pass-row { align-items: stretch; flex-direction: column; } .importonbridge-pass code { width: 100%; } .importonbridge-ai-summary { grid-template-columns: 1fr 1fr; } .importonbridge-form-inline { grid-template-columns: 1fr; } .importonbridge-field-actions { width: 100%; } .importonbridge-btn-row .importonbridge-btn { width: 100%; } }',
				'@media (max-width: 480px) { .importonbridge-wrap { margin: 8px auto; padding-bottom: 80px; } .importonbridge-hero { padding: 14px; } .importonbridge-hero-copy h1 { font-size: 14px; } .importonbridge-card { padding: 12px; border-radius: 6px; } .importonbridge-card-head { flex-direction: column; gap: 8px; } .importonbridge-card-head h2 { font-size: 12px; } .importonbridge-card-head p { font-size: 10px; } .importonbridge-ai-summary { grid-template-columns: 1fr; } .importonbridge-ai-summary-item { padding: 10px 12px; } .importonbridge-ai-summary-label { font-size: 8px; } .importonbridge-ai-summary-value { font-size: 12px; } .importonbridge-accordion summary { padding: 12px; flex-wrap: wrap; } .importonbridge-accordion-title { font-size: 12px; } .importonbridge-accordion-meta { font-size: 10px; width: 100%; } .importonbridge-kv { grid-template-columns: 1fr; gap: 8px; } .importonbridge-v { width: 100%; } .importonbridge-form { grid-template-columns: 1fr; } .importonbridge-status-pill { font-size: 9px; padding: 3px 8px; } .importonbridge-stat { padding: 10px; } .importonbridge-stat-label { font-size: 9px; } .importonbridge-stat-value { font-size: 18px; } .importonbridge-table-wrap { font-size: 11px; } .importonbridge-table th, .importonbridge-table td { padding: 8px; } .importonbridge-field input, .importonbridge-field textarea, .importonbridge-field select, .importonbridge-v input, .importonbridge-v textarea { font-size: 11px; padding: 8px 10px; } .importonbridge-field-help { font-size: 10px; } .importonbridge-btn, .importonbridge-copy, .importonbridge-ghost-btn { font-size: 10px; padding: 10px 12px; min-height: 38px; } .importonbridge-note-box { padding: 10px; font-size: 11px; } .importonbridge-help-tooltip { width: 200px; font-size: 10px; } }',
				/* ── WordPress admin fixes ────────────────────────────────────── */
				'#wpcontent { overflow-x: hidden; }',
				'@media (max-width: 782px) { #wpcontent { padding-bottom: 60px; } }',
				'@media (max-width: 480px) { #wpcontent { padding-bottom: 50px; } }',
				'.wrap.importonbridge-wrap { position: relative; z-index: 1; }',
				'.importonbridge-page { min-height: 500px; padding-bottom: 80px; overflow: hidden; }',
				'@media (max-width: 782px) { .importonbridge-page { min-height: auto; padding-bottom: 60px; } }',
				'@media (max-width: 480px) { .importonbridge-page { padding-bottom: 50px; } }',
				/* ── Usage page stats responsive ──────────────────────────────── */
				'.importonbridge-stats-row { display: grid; gap: 16px; }',
				'@media (min-width: 600px) { .importonbridge-stats-row { grid-template-columns: repeat(2, 1fr); } }',
				'@media (min-width: 1080px) { .importonbridge-stats-row { grid-template-columns: repeat(4, 1fr); } }',
				'@media (max-width: 480px) { .importonbridge-stats-row .importonbridge-stat-box { padding: 12px; } .importonbridge-stats-row .importonbridge-stat-box > div:first-child { font-size: 9px; } .importonbridge-stats-row .importonbridge-stat-box > div:last-child { font-size: 20px; } }',
				/* ── Connect page ─────────────────────────────────────────────── */
				'.importonbridge-connect-top { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }',
				'.importonbridge-shell a.importonbridge-download-link { font-size: 11px; font-weight: 600; color: var(--text-dim); text-decoration: none; padding: 6px 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); font-family: var(--mono); transition: color 0.2s, border-color 0.2s, background 0.2s; }',
				'.importonbridge-shell a.importonbridge-download-link:hover { color: var(--text); border-color: var(--text-dim); background: var(--bg); }',
				/* ── Connect page hero ─────────────────────────────────────────── */
				'@keyframes fadeSlideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }',
				'.importonbridge-connect-hero { text-align: center; padding: 60px 32px 48px; margin-bottom: 0; max-width: 560px; margin-left: auto; margin-right: auto; animation: fadeSlideUp 0.5s ease-out; position: relative; }',
				'.importonbridge-connect-hero::before { content: ""; position: absolute; top: 3px; left: 3px; width: 8px; height: 8px; border-top: 1px solid var(--text-faint); border-left: 1px solid var(--text-faint); pointer-events: none; }',
				'.importonbridge-connect-hero::after { content: ""; position: absolute; bottom: 3px; right: 3px; width: 8px; height: 8px; border-bottom: 1px solid var(--text-faint); border-right: 1px solid var(--text-faint); pointer-events: none; }',
				'.importonbridge-hero-icon { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 24px; background: var(--bg); color: var(--text-dim); margin-bottom: 24px; transition: transform 0.3s ease, box-shadow 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }',
				'.importonbridge-connect-hero:hover .importonbridge-hero-icon { transform: translateY(-4px) scale(1.05); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }',
				'.importonbridge-connect-hero h1 { margin: 0 0 8px; font-size: 24px; font-weight: 800; color: var(--text); letter-spacing: -0.03em; font-family: var(--mono); }',
				'.importonbridge-hero-sub { margin: 0 0 32px; color: var(--text-dim); font-size: 13px; line-height: 1.6; max-width: 400px; margin-left: auto; margin-right: auto; font-family: var(--mono); }',
				'.importonbridge-hero-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 20px; }',
				'.importonbridge-shell a.importonbridge-btn-primary, .importonbridge-shell button.importonbridge-btn-primary { display: inline-flex; align-items: center; gap: 8px; padding: 13px 28px; border-radius: 4px; font-weight: 700; font-size: 12px; cursor: pointer; text-decoration: none; border: 1px solid var(--text); background: var(--text); color: #fff; font-family: var(--mono); letter-spacing: 0.03em; transition: all 0.2s ease; }',
				'.importonbridge-shell a.importonbridge-btn-primary:hover, .importonbridge-shell button.importonbridge-btn-primary:hover { background: #000; border-color: #000; color: #fff; }',
				'.importonbridge-shell a.importonbridge-btn-primary:active, .importonbridge-shell button.importonbridge-btn-primary:active { transform: translateY(0); }',
				'.importonbridge-shell a.importonbridge-btn-primary--danger, .importonbridge-shell button.importonbridge-btn-primary--danger { border-color: #dc2626; background: #dc2626; }',
				'.importonbridge-shell a.importonbridge-btn-primary--danger:hover, .importonbridge-shell button.importonbridge-btn-primary--danger:hover { background: #b91c1c; border-color: #b91c1c; }',
				'.importonbridge-shell a.importonbridge-btn-primary--neutral, .importonbridge-shell button.importonbridge-btn-primary--neutral { border-color: var(--text); background: var(--text); }',
				'.importonbridge-shell a.importonbridge-btn-primary--neutral:hover, .importonbridge-shell button.importonbridge-btn-primary--neutral:hover { background: #000; border-color: #000; }',
				'.importonbridge-shell button.importonbridge-btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }',
				/* ── Connect card ──────────────────────────────────────────────── */
				'.importonbridge-card--connect { max-width: 520px; margin: 0 auto; padding: 0; border-radius: 8px; border: 1px solid var(--border); background: var(--card); overflow: hidden; }',
				'.importonbridge-connect-header { display: flex; align-items: center; gap: 12px; padding: 16px 24px; background: var(--bg); border-bottom: 1px solid var(--border); }',
				'.importonbridge-status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--text-faint); flex: 0 0 auto; }',
				'.importonbridge-status-label { font-size: 11px; font-weight: 700; color: var(--text); font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.08em; }',
				'.importonbridge-connect-body { padding: 28px 24px 20px; text-align: center; }',
				'.importonbridge-connect-info { display: grid; gap: 0; text-align: left; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 20px; }',
				'.importonbridge-info-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); }',
				'.importonbridge-info-row:last-child { border-bottom: none; }',
				'.importonbridge-info-row .importonbridge-info-label { display: block; margin: 0; color: var(--text-dim); font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; font-family: var(--mono); flex: 0 0 auto; }',
				'.importonbridge-info-value { font-size: 12px; color: var(--text); text-align: right; overflow-wrap: anywhere; font-family: var(--mono); }',
				'.importonbridge-info-value code { font-size: 11px; background: var(--card); padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border); user-select: all; font-family: var(--mono); }',
				'.importonbridge-connect-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-bottom: 16px; }',
				'.importonbridge-shell button.importonbridge-btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 11px 22px; border-radius: 4px; font-weight: 600; font-size: 11px; cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--text); font-family: var(--mono); transition: all 0.2s ease; }',
				'.importonbridge-shell button.importonbridge-btn-secondary:hover { border-color: var(--text); background: var(--bg); color: var(--text); }',
				'.importonbridge-connect-footer { display: flex; justify-content: center; padding: 12px 24px; background: var(--bg); border-top: 1px solid var(--border); }',
				'.importonbridge-shell button.importonbridge-btn-text { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: none; background: none; color: var(--text-dim); font-size: 10px; font-weight: 500; cursor: pointer; border-radius: 4px; font-family: var(--mono); transition: all 0.2s ease; }',
				'.importonbridge-shell button.importonbridge-btn-text:hover { color: var(--text); background: var(--bg); }',
				/* ── Pulse animation ──────────────────────────────────────────── */
				'@keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.1); } }',
				/* ── Modal ─────────────────────────────────────────────────────── */
				'.importonbridge-modal-overlay { position: fixed; inset: 0; z-index: 100000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); animation: fadeIn 0.2s ease-out; }',
				'@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }',
				'.importonbridge-modal { background: var(--card); border-radius: 8px; max-width: 480px; width: calc(100% - 32px); max-height: 80vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.25); animation: modalSlideUp 0.3s ease-out; }',
				'@keyframes modalSlideUp { from { opacity: 0; transform: translateY(24px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }',
				'.importonbridge-modal-header { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 28px 24px 12px; text-align: center; }',
				'.importonbridge-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text); font-family: var(--mono); }',
				'.importonbridge-modal-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--bg); color: var(--accent); }',
				'.importonbridge-modal-body { padding: 12px 24px 20px; }',
				'.importonbridge-modal-footer { padding: 0 24px 24px; justify-content: center; }',
				'.importonbridge-modal-footer .importonbridge-btn-primary { min-height: 42px; padding: 10px 24px; }',
				/* ── Steps ─────────────────────────────────────────────────────── */
				'.importonbridge-steps { display: grid; gap: 8px; }',
				'.importonbridge-step { display: grid; grid-template-columns: 32px 1fr; gap: 12px; padding: 12px 14px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); transition: all 0.3s ease; }',
				'.importonbridge-step--active { background: var(--bg); border-color: var(--text); }',
				'.importonbridge-step--done { background: var(--bg); border-color: var(--border); }',
				'.importonbridge-step--warning { background: var(--bg); border-color: var(--border); }',
				'.importonbridge-step--error { background: var(--bg); border-color: var(--border); }',
				'.importonbridge-step--pending { opacity: 0.5; }',
				'.importonbridge-step-badge { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 12px; font-weight: 700; color: var(--card); background: var(--text-faint); transition: all 0.3s ease; flex: 0 0 auto; font-family: var(--mono); }',
				'.importonbridge-step--active .importonbridge-step-badge { background: var(--text); }',
				'.importonbridge-step--done .importonbridge-step-badge { background: var(--text); }',
				'.importonbridge-step--warning .importonbridge-step-badge { background: #d97706; }',
				'.importonbridge-step--error .importonbridge-step-badge { background: #dc2626; }',
				'.importonbridge-step-content { min-width: 0; display: grid; gap: 2px; }',
				'.importonbridge-step-title { font-size: 12px; font-weight: 600; color: var(--text); font-family: var(--mono); }',
				'.importonbridge-step--pending .importonbridge-step-title { color: var(--text-dim); }',
				'.importonbridge-step-desc { font-size: 11px; color: var(--text-dim); font-family: var(--mono); line-height: 1.4; }',
				'.importonbridge-step--active .importonbridge-step-desc { color: var(--text); }',
				/* ── Terms checkbox ────────────────────────────────────────────── */
				'.importonbridge-terms-checkbox { display: inline-flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; cursor: pointer; transition: all 0.15s ease; margin: 0; }',
				'.importonbridge-terms-checkbox:hover { background: var(--card); border-color: var(--text-dim); }',
				'.importonbridge-terms-checkbox input[type="checkbox"] { width: 18px; height: 18px; border: 2px solid var(--text-faint); border-radius: 4px; cursor: pointer; accent-color: var(--text); flex: 0 0 auto; transition: border-color 0.15s ease; }',
				/* ── URL Import Pro Lock ─────────────────────────────────────────── */
				'.importonbridge-locked-wrapper { position: relative; }',
				'.importonbridge-locked-wrapper.importonbridge-locked .importonbridge-grid, .importonbridge-locked-wrapper.importonbridge-locked .importonbridge-card--recent { filter: blur(0.8px); opacity: 0.92; pointer-events: none; user-select: none; }',
				'.importonbridge-premium-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 10; padding: 32px 20px; }',
				'.importonbridge-premium-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.9), transparent); }',
				'.importonbridge-premium-card { position: relative; max-width: 520px; width: 100%; background: linear-gradient(180deg, #fff 0%, #eff6ff 100%); border: 1px solid #bfdbfe; border-radius: 16px; padding: 32px 26px; text-align: center; box-shadow: 0 18px 40px rgba(0,110,252,0.08), 0 1px 0 #fff inset; overflow: hidden; }',
				'.importonbridge-premium-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 9999px; background: linear-gradient(135deg, #fff 0%, #eff6ff 100%); border: 1px solid #bfdbfe; color: #004bb5; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(0,110,252,0.12), inset 0 1px 0 #fff; }',
				'.importonbridge-premium-icon { width: 56px; height: 56px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; border-radius: 16px; background: radial-gradient(120% 120% at 30% 20%, #eff6ff 0%, #dbeafe 18%, #006EFC 58%, #0052cc 100%); color: #fff; border: 1px solid rgba(255,255,255,0.55); box-shadow: 0 10px 24px rgba(0,110,252,0.25), inset 0 1px 0 rgba(255,255,255,0.65); }',
				'.importonbridge-premium-card h3 { margin: 0 0 8px; font-size: 16px; font-weight: 700; color: var(--text); }',
				'.importonbridge-premium-card h3 span { color: var(--text); }',
				'.importonbridge-premium-card p { margin: 0 0 18px; color: var(--text-dim); font-size: 12px; line-height: 1.5; }',
				'.importonbridge-premium-features { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: left; margin: 0 0 20px; }',
				'.importonbridge-premium-feature { display: flex; align-items: center; gap: 8px; padding: 9px 12px; background: #fff; border: 1px solid #dbeafe; border-radius: 8px; font-size: 11px; color: #1e3a8a; font-weight: 600; box-shadow: 0 1px 0 #fff; }',
				'.importonbridge-premium-feature svg { flex: 0 0 auto; color: #f59e0b; }',
				'.importonbridge-shell a.importonbridge-premium-cta, .importonbridge-shell a.importonbridge-premium-cta:visited { display: inline-flex; align-items: center; gap: 8px; padding: 13px 26px; border-radius: 9999px; background: linear-gradient(135deg, #006EFC 0%, #006EFC 45%, #3b82f6 100%) !important; color: #fff !important; font-weight: 800; font-size: 12px; letter-spacing: 0.02em; text-decoration: none !important; border: 1px solid #0052cc; box-shadow: 0 8px 20px rgba(0,110,252,0.35), inset 0 1px 0 rgba(255,255,255,0.4); }',
				'.importonbridge-shell a.importonbridge-premium-cta:hover { background: linear-gradient(135deg, #0052cc 0%, #006EFC 100%) !important; border-color: #004bb5 !important; color: #fff !important; transform: translateY(-1px); box-shadow: 0 12px 28px rgba(0,110,252,0.3); }',
				'.importonbridge-premium-note { margin-top: 12px; font-size: 10px; color: var(--text-dim); }',
																																																								'#adminmenu .wp-submenu a[href*="page=importon-bridge-pricing"] { display: block !important; font-weight: 600 !important; }',
				'.importonbridge-menu-premium { display: inline !important; margin-left: 6px !important; padding: 0 !important; font-size: 10px !important; font-weight: 600 !important; letter-spacing: 0 !important; text-transform: none !important; background: none !important; background-color: transparent !important; color: #f59e0b !important; border: none !important; box-shadow: none !important; text-shadow: none !important; vertical-align: baseline !important; }',
				'@media (max-width: 600px) { .importonbridge-premium-features { grid-template-columns: 1fr; } .importonbridge-premium-card { padding: 28px 20px; } }',
				'.importonbridge-terms-checkbox input[type="checkbox"]:checked { border-color: var(--text); }',
				'.importonbridge-terms-checkbox input[type="checkbox"]:focus-visible { outline: 2px solid var(--text-faint); outline-offset: 2px; }',
				'.importonbridge-terms-text { font-size: 11px; color: var(--text); user-select: none; line-height: 1.5; font-family: var(--mono); }',
				'.importonbridge-terms-text a { color: var(--accent); text-decoration: none; font-weight: 600; border-bottom: 1px solid transparent; transition: border-color 0.15s ease; }',
				'.importonbridge-terms-text a:hover { color: var(--accent); border-bottom-color: var(--accent); }',
			)
		);
	}

	// ── USAGE page ───────────────────────────────────────────────────────────

	public static function render_usage_page(): void {
		self::assert_access();

		global $wpdb;
		$table = esc_sql( preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'importonbridge_usage_log' ) );

		if ( isset( $_POST['importonbridge_clear_usage'] ) && check_admin_referer( 'importonbridge_clear_usage_action', 'importonbridge_clear_usage_nonce' ) ) {
			$wpdb->query( $wpdb->prepare( "TRUNCATE TABLE `{$table}`" ) );
		}

		$model_totals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT model, provider,
					SUM(input_tokens)  AS total_input,
					SUM(output_tokens) AS total_output,
					SUM(cost_usd)      AS total_cost,
					COUNT(*)           AS calls
				FROM {$table}
				GROUP BY model, provider
				ORDER BY total_cost DESC"
			),
			ARRAY_A
		) ?: array();

		$grand = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(input_tokens)         AS input,
					SUM(output_tokens)        AS output,
					SUM(cost_usd)             AS cost,
					COUNT(DISTINCT product_id) AS products
				FROM {$table}"
			),
			ARRAY_A
		) ?: array();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, product_title, model, provider,
					SUM(input_tokens)  AS input_tok,
					SUM(output_tokens) AS output_tok,
					SUM(cost_usd)      AS cost,
					MAX(created_at)    AS last_run
				FROM {$table}
				GROUP BY product_id, model, provider
				ORDER BY last_run DESC
				LIMIT 200"
			),
			ARRAY_A
		) ?: array();

		$fmt_cost = static function ( $usd ): string {
			$usd = (float) $usd;
			if ( $usd <= 0 )    return '$0.0000';
			if ( $usd >= 0.01 ) return '$' . number_format( $usd, 4 );
			return number_format( $usd * 100, 4 ) . '¢';
		};

		$total_products = (int) ( $grand['products'] ?? 0 );
		$total_input    = (int) ( $grand['input']    ?? 0 );
		$total_output   = (int) ( $grand['output']   ?? 0 );
		$total_cost     = $fmt_cost( $grand['cost'] ?? 0 );
		?>
		<div class="importonbridge-wrap importonbridge-shell importonbridge-page">

			<div class="importonbridge-hero importonbridge-hero--import">
				<div class="importonbridge-hero-copy">
					<h1>AI Usage</h1>
					<p>Exact token counts and costs per product import. Updates each time you run a URL import or manual import.</p>
				</div>
				<div class="importonbridge-hero-side">
					<div class="importonbridge-hero-actions">
						<a class="importonbridge-ghost-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=importon-bridge' ) ); ?>">Settings</a>
					</div>
				</div>
			</div>

			<div class="importonbridge-run-stats-grid" style="margin-top:12px;">
				<div class="importonbridge-stat importonbridge-stat--compact">
					<div class="importonbridge-stat-label">Products Logged</div>
					<div class="importonbridge-stat-value"><?php echo esc_html( number_format( $total_products ) ); ?></div>
				</div>
				<div class="importonbridge-stat importonbridge-stat--compact">
					<div class="importonbridge-stat-label">Input Tokens</div>
					<div class="importonbridge-stat-value"><?php echo esc_html( number_format( $total_input ) ); ?></div>
				</div>
				<div class="importonbridge-stat importonbridge-stat--compact">
					<div class="importonbridge-stat-label">Output Tokens</div>
					<div class="importonbridge-stat-value"><?php echo esc_html( number_format( $total_output ) ); ?></div>
				</div>
				<div class="importonbridge-stat importonbridge-stat--compact">
					<div class="importonbridge-stat-label">Total Cost</div>
					<div class="importonbridge-stat-value"><?php echo esc_html( $total_cost ); ?></div>
				</div>
			</div>

			<div class="importonbridge-grid importonbridge-grid--tables" style="margin-top:12px;">

				<div class="importonbridge-card importonbridge-card--section">
					<div class="importonbridge-card-head">
						<div>
							<h2>Per Model</h2>
							<p>Cumulative token spend and cost per AI model used.</p>
						</div>
					</div>
					<?php if ( empty( $model_totals ) ) : ?>
						<p class="importonbridge-empty-state">No usage yet — import a product first.</p>
					<?php else : ?>
					<div class="importonbridge-table-wrap">
						<table class="widefat striped importonbridge-table">
							<thead>
								<tr>
									<th>Model</th>
									<th>Provider</th>
									<th style="text-align:right;">Input</th>
									<th style="text-align:right;">Output</th>
									<th style="text-align:right;">Calls</th>
									<th style="text-align:right;">Cost</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $model_totals as $mt ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $mt['model'] ); ?></strong></td>
									<td><span class="importonbridge-status-pill importonbridge-status-pill--neutral"><?php echo esc_html( $mt['provider'] ); ?></span></td>
									<td style="text-align:right;"><?php echo esc_html( number_format( (int) $mt['total_input'] ) ); ?></td>
									<td style="text-align:right;"><?php echo esc_html( number_format( (int) $mt['total_output'] ) ); ?></td>
									<td style="text-align:right;"><?php echo (int) $mt['calls']; ?></td>
									<td style="text-align:right;font-weight:600;"><?php echo esc_html( $fmt_cost( $mt['total_cost'] ) ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>

				<div class="importonbridge-card importonbridge-card--section importonbridge-card--recent">
					<div class="importonbridge-card-head">
						<div>
							<h2>Per Product Log</h2>
							<p>Most recent 200 product imports with token and cost breakdown.</p>
						</div>
						<form method="post" style="margin:0;flex-shrink:0;">
							<?php wp_nonce_field( 'importonbridge_clear_usage_action', 'importonbridge_clear_usage_nonce' ); ?>
							<button type="submit" name="importonbridge_clear_usage" value="1" class="importonbridge-ghost-btn" onclick="return confirm('Clear all usage data? This cannot be undone.');">Clear Log</button>
						</form>
					</div>
					<?php if ( empty( $rows ) ) : ?>
						<p class="importonbridge-empty-state">No product usage logged yet.</p>
					<?php else : ?>
					<div class="importonbridge-table-wrap importonbridge-table-wrap--recent">
						<table class="widefat striped importonbridge-table importonbridge-run-table">
							<thead>
								<tr>
									<th>Product</th>
									<th>Model</th>
									<th style="text-align:right;">In tok</th>
									<th style="text-align:right;">Out tok</th>
									<th style="text-align:right;">Cost</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td>
										<?php if ( (int) $row['product_id'] > 0 ) : ?>
											<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['product_id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['product_title'] ?: 'Product #' . $row['product_id'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $row['product_title'] ?: '—' ); ?>
										<?php endif; ?>
									</td>
									<td><span class="importonbridge-muted"><?php echo esc_html( $row['model'] ); ?></span></td>
									<td style="text-align:right;"><?php echo esc_html( number_format( (int) $row['input_tok'] ) ); ?></td>
									<td style="text-align:right;"><?php echo esc_html( number_format( (int) $row['output_tok'] ) ); ?></td>
									<td style="text-align:right;font-weight:600;"><?php echo esc_html( $fmt_cost( $row['cost'] ) ); ?></td>
									<td><span class="importonbridge-muted"><?php echo esc_html( $row['last_run'] ); ?></span></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
	}

}
