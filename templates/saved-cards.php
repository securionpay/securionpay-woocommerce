<?php
/**
 * The Template for displaying the saved credit cards on the account page
 *
 * Override this template by copying it to yourtheme/woocommerce/securionpay4wc/saved-cards.php
 */

if (!defined('ABSPATH')) {
	exit(); // Exit if accessed directly
}

?>
<h2 id="saved-cards"><?php _e('Saved cards', 'securionpay-for-woocommerce'); ?></h2>

<?php 
wc_print_notices();
?>

<table class="shop_table">
	<thead>
		<tr>
			<th><?php _e('Brand', 'securionpay-for-woocommerce'); ?></th>
			<th><?php _e('Card number', 'securionpay-for-woocommerce'); ?></th>
			<th><?php _e('Expiration', 'securionpay-for-woocommerce'); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<?php 
	foreach ($cards as $i => $card) {
	?>
		<tr>
			<td><?php echo $card['brand']; ?></td>
			<td>&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; <?php echo $card['last4']; ?></td>
			<td><?php echo $card['expMonth'] . '/' . $card['expYear']; ?></td>
			<td>
				<form action="#saved-cards" method="POST">
					<?php wp_nonce_field('securionpay4wc-delete-card'); ?>
					<input type="hidden" name="securionpay4wc-delete-card" value="<?php echo $i; ?>">
					<input type="submit" value="<?php _e('Delete card', 'securionpay-for-woocommerce'); ?>">
				</form>
			</td>
		</tr>
	<?php 
	}
	?>
	</tbody>
</table>
