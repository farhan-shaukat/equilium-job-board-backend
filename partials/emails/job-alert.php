<p>Thank you for subscribing to our job alert system.</p>
<p>We have a new job posting that matches your search parameters. Please click to the link below to view and apply.</p>
<p></p>
<p><a href="<?php echo get_permalink($job_id); ?>">View Job</a></p>
<hr>
<?php if ( ! empty($unsubscribe_link) ) { ?>
    <p>To stop receiving job alert emails for there search parameters, please click the link below to unsubscribe.</p>
    <p><a href="<?php echo esc_url($unsubscribe_link); ?>">Unsubscribe</a></p>
<?php } ?>