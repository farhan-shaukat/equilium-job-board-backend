<?php
$date = new DateTime();
$date->setTimestamp( $expiration );
$date->setTimezone( wp_timezone());

$time_str = date_format( $date , 'H:i' );
?>
<p>A password reset was requested for your account and your password reset code is <?php echo $code; ?>.</p>
<?php if( $expiration !== 0 ) { ?>
    <p>Please note that this code will expire at <?php echo $time_str; ?>.</p>
<?php } ?>
