<?php global $__tp_park_info, $__tp_attraction; ?>

<div class="parks-chart--overview">
    <?php foreach($__tp_park_info->get_wait_data_charts($__tp_attraction ?? []) as $__data): ?>
        <h3><?php echo esc_html($__data['title']); ?></h3>
        <div class="js-chart-element" data-wait="<?php echo esc_attr(json_encode($__data['data'])); ?>"
             data-wait-date="<?php echo esc_attr($__data['date']); ?>"
             data-vaxis-title="<?php echo esc_attr($__data['vaxis-title']); ?>"
             style="width: 100%;height: 500px"></div>
    <?php endforeach; ?>
</div>