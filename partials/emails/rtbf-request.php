<?php
    $current_user = wp_get_current_user();
?>
<p><?php echo $current_user->display_name; ?> has issued an RTBF request</p>
