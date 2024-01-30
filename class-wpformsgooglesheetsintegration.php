<?php
/**
 * WPFormsGooglesheetsIntegration
 *
 * @package WPFormsGooglesheetsIntegration
 */

/**
 * Plugin Name: WPForms Googlesheets Integration
 * Plugin URI: https://github.com/bsetiawan88
 * Description: WPForms Googlesheets Integration
 * Author: Bagus Pribadi Setiawan
 * Author URI: https://github.com/bsetiawan88
 * Version: 1.0.0
 * Copyright: (c) 2021
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.4
 * Text Domain: wpforms-googlesheets
 * Domain Path: language
 */
class WPFormsGooglesheetsIntegration {

	/**
	 * The singleton instance of the class.
	 *
	 * @var Object|null
	 **/
	private static $_instance = null;// @codingStandardsIgnoreLine

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @return object $_instance An instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return ( self::$_instance );
	}

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		require_once __DIR__ . '/vendor/autoload.php';

		// create settings tab.
		add_filter(
			'wpforms_settings_tabs',
			function ( $tabs ) {
				$tabs['googlesheets'] = array(
					'name'   => __( 'Google Sheets', 'wpforms-googlesheets' ),
					'form'   => true,
					'submit' => __( 'Save setting', 'wpforms-googlesheets' ),
				);

				return $tabs;
			}
		);

		// create configuration page.
		add_filter(
			'wpforms_settings_defaults',
			function ( $settings ) {
				$settings['googlesheets'] = array(
					'googlesheets-heading'       => array(
						'id'       => 'googlesheets-heading',
						'content'  => $this->get_googlesheets_field_desc(),
						'type'     => 'content',
						'no_label' => true,
						'class'    => array( 'section-heading' ),
					),
					'googlesheets-client-id'     => array(
						'id'   => 'googlesheets-client-id',
						'name' => __( 'Client ID', 'wpforms-googlesheets' ),
						'type' => 'text',
					),
					'googlesheets-client-secret' => array(
						'id'   => 'googlesheets-client-secret',
						'name' => __( 'Client Secret', 'wpforms-googlesheets' ),
						'type' => 'text',
					),
				);

				return $settings;
			}
		);

		// add filter for custom page template.
		add_filter(
			'wpforms_helpers_templates_get_theme_template_paths',
			function ( $paths ) {
				$paths[200] = plugin_dir_path( __FILE__ ) . '/templates';

				return $paths;
			}
		);

		// insert data into Google Spreadsheet.
		add_action(
			'wpforms_process_complete',
			function ( $fields, $entry, $form_data, $entry_id ) {
				try {
					$client = $this->get_client( false );
					if ( $client ) {
						$service = new Google_Service_Sheets( $client );
						$values  = array();

						$spreadsheet_id = get_post_meta( $form_data['id'], 'wp_forms_googlesheets_spreadsheet_id', true );
						if ( ! $spreadsheet_id ) {
							// create spreadsheet.
							$spreadsheet = new Google_Service_Sheets_Spreadsheet(
								array(
									'properties' => array(
										'title' => $form_data['settings']['form_title'],
									),
								)
							);

							$spreadsheet = $service->spreadsheets->create(
								$spreadsheet,
								array(
									'fields' => 'spreadsheetId',
								)
							);

							$spreadsheet_id = $spreadsheet->spreadsheetId;// @codingStandardsIgnoreLine
							update_post_meta( $form_data['id'], 'wp_forms_googlesheets_spreadsheet_id', $spreadsheet_id );

							// insert spreadsheet header.
							$values[] = array_column( $fields, 'name' );
						}

						$values[] = array_column( $fields, 'value' );
						$body     = new Google_Service_Sheets_ValueRange(
							array(
								'values' => $values,
							)
						);
						$params   = array(
							'valueInputOption' => 'RAW',
						);
						$result   = $service->spreadsheets_values->append( $spreadsheet_id, 'Sheet1!A1:' . $this->number_to_letter( count( $fields ) - 1 ) . '1', $body, $params );
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( print_r( $e->getMessage(), true ) );//@codingStandardsIgnoreLine
					}
				}
			},
			10,
			4
		);

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Action for plugins_loaded.
	 *
	 * @return void
	 */
	public function init() {
		if ( isset( $_GET['page'] ) && isset( $_GET['view'] ) && 'wpforms-settings' === $_GET['page'] && 'googlesheets' === $_GET['view'] && ( isset( $_GET['authorize'] ) || isset( $_GET['code'] ) ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// begin Google OAuth2 and save access token.
			$this->get_client();
		};
	}

	/**
	 * This function will return the field description.
	 *
	 * @return string
	 */
	private function get_googlesheets_field_desc() {
		return wpforms_render( 'googlesheets-description' );
	}

	/**
	 * Function to get the Google client class
	 *
	 * @param boolean $redirect Whether to redirect or not.
	 * @return null|object
	 */
	private function get_client( $redirect = true ) {
		$client_id     = wpforms_setting( 'googlesheets-client-id' );
		$client_secret = wpforms_setting( 'googlesheets-client-secret' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return;
		}

		$client = new Google\Client();
		$client->setAuthConfig(
			array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			)
		);
		$client->addScope( Google_Service_Sheets::SPREADSHEETS );
		$client->setAccessType( 'offline' );
		$redirect_url = admin_url( 'admin.php?page=wpforms-settings&view=googlesheets' );

		$access_token = get_option( 'wp_forms_googlesheets_access_token' );
		if ( $access_token ) {
			$client->setAccessToken( (array) json_decode( $access_token ) );
		}

		if ( $client->isAccessTokenExpired() ) {
			if ( $client->getRefreshToken() && $redirect ) {
				$client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );
			} elseif ( $redirect ) {
				$client->setAccessType( 'offline' );
				$client->setRedirectUri( $redirect_url );
				header( 'Location: ' . filter_var( $client->createAuthUrl(), FILTER_SANITIZE_URL ) );
			} else {
				return false;
			}
		}

		if ( isset( $_GET['code'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$client->authenticate( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( 'wp_forms_googlesheets_access_token', wp_json_encode( $client->getAccessToken() ) );
			header( 'Location: ' . filter_var( $redirect_url, FILTER_SANITIZE_URL ) );
		}

		return $client;
	}

	/**
	 * Function to convert number to letter
	 *
	 * @param integer $n The number to covert to letter.
	 * @return string
	 */
	private function number_to_letter( $n ) {
		$r = '';
		for ( $i = 1; $n >= 0 && $i < 10; $i++ ) {
			$r  = chr( 0x41 + ( $n % pow( 26, $i ) / pow( 26, $i - 1 ) ) ) . $r;
			$n -= pow( 26, $i );
		}
		return $r;
	}

}

WPFormsGooglesheetsIntegration::get_instance();
