<?php 

$jobs = get_option('WC_MYOB_job_codes_list' );
$product_job = get_post_meta($post->ID, '_myob_product_job_code', true);
?>
<label>MYOB Job Code</label><br>
<?php wp_nonce_field( 'save_myob_nonce', 'myob_job_nonce' ); ?>
<select name="myob_product_job_code">
	<option value=""> Select Job Code </option>
	<?php if (is_array($jobs)) : ?>
		<?php foreach ($jobs as $j => $value) : ?>
			<?php if ($product_job == $j) : ?>
				<option value="<?php echo esc_attr($j); ?>" selected> <?php echo esc_html($value); ?></option>
				<?php else : ?>
					<option value="<?php echo esc_attr($j); ?>"> <?php echo esc_html($value); ?></option>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</select>
