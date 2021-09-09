<?php

class WidgetPreview {
    protected $shortcode;

    public function __construct() {
        require_once S2PATH . 'classes/class-s2-frontend.php';
        add_action( 'wp_ajax_s2_preview', [$this, 'render_widget_element'] );

        if ( isset( $_GET['action'], $_GET['shortcode'] ) ) {
            $this->shortcode = $_GET['shortcode'] ;
        }
    }

    public function render_widget_element()
    {
        echo do_shortcode(base64_decode($this->shortcode));
        exit(0);
    }
}