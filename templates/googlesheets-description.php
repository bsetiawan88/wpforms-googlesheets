<h4><?php echo __( 'Google Sheets', 'wpforms-googlesheets' ); ?></h4>
<p><?php echo __( 'Manage integrations with Google Sheets.', 'wpforms-googlesheets' ); ?></p>
<ol>
<li><?php echo __( 'Create new Google Sheets API service in ', 'wpforms-googlesheets' ); ?><a href="https://console.developers.google.com">https://console.developers.google.com</a></li>
<li><?php echo __( 'Create OAuth credentials', 'wpforms-googlesheets' ); ?></li>
<li><?php echo __( 'Put following URL as <b>Authorized redirect URIs</b>: ', 'wpforms-googlesheets' ) . '<b style="color:red">' . admin_url( 'admin.php?page=wpforms-settings&view=googlesheets' ) . '</b>'; ?></li>
<li><?php echo __( 'Put Client ID and Client Secret into boxes below', 'wpforms-googlesheets' ); ?></li>
<li><?php echo __( 'Click <b>Save setting</b>', 'wpforms-googlesheets' ); ?></li>
<li><?php echo __( 'Click <b>Authorize</b> button', 'wpforms-googlesheets' ); ?></li>
</ol>

<?php
$client_id     = wpforms_setting( 'googlesheets-client-id' );
$client_secret = wpforms_setting( 'googlesheets-client-secret' );

if ( ! empty( $client_id ) && ! empty( $client_secret ) ) {
	?>
	<a href="<?php echo admin_url( 'admin.php?page=wpforms-settings&view=googlesheets&authorize=true' ); ?>" class="wpforms-btn wpforms-btn-md wpforms-btn-blue"><?php echo __( 'Authorize', 'wpforms-googlesheets' ); ?></a>
	<?php

}
?>
