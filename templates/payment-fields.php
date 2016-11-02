<?php
/**
 * The Template for displaying the credit card form on the checkout page
 *
 * Override this template by copying it to yourtheme/woocommerce/securionpay4wc/payment-fields.php
 */

if (!defined('ABSPATH')) {
	exit(); // Exit if accessed directly
}

if ($description) {
	?>
	<p class="securionpay4wc-description"><?php echo $description; ?></p>
	<?php 
}

if ($cards) {

	foreach ($cards as $i => $card) {
		$label = sprintf('%s - &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;%s (%s/%s)',
				$card['brand'], $card['last4'], $card['expMonth'], $card['expYear']); 
		?>
		<input type="radio" id="securionpay4wc-card-<?php echo $i; ?>" name="securionpay4wc-card" value="<?php echo $i; ?>" <?php echo ($selectedCard == $i) ? 'checked="checked"' : ''; ?>>
		<label for="securionpay4wc-card_<?php echo $i; ?>"><?php echo $label; ?></label><br>
		<?php 
	}
	
	?>
    <input type="radio" id="new_card" name="securionpay4wc-card" value="new" <?php echo ($selectedCard == 'new') ? 'checked="checked"' : ''; ?>>
    <label for="new_card"><?php _e('Use a new credit card', 'securionpay-for-woocommerce'); ?></label>	
	<?php 
}	
