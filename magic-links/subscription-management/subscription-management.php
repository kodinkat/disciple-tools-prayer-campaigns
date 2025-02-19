<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DT_Prayer_Subscription_Management_Magic_Link extends DT_Magic_Url_Base {

    public $post_type = 'subscriptions';
    public $page_title = 'My Prayer Times';

    public $magic = false;
    public $parts = [];
    public $root = 'subscriptions_app'; // define the root of the url {yoursite}/root/type/key/action
    public $type = 'manage'; // define the type
    public $type_name = 'Subscriptions';
    public $type_actions = [
        '' => 'Manage',
        'download_calendar' => 'Download Calendar',
    ];

    public function __construct(){
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        parent::__construct();
        if ( !$this->check_parts_match() ){
            return;
        }
        $post = DT_Posts::get_post( 'subscriptions', $this->parts['post_id'], true, false );
        if ( is_wp_error( $post ) || empty( $post['campaigns'] ) ){
            return;
        }
        if ( isset( $post['lang'] ) && $post['lang'] !== 'en_US' ){
            $lang_code = $post['lang'];
            add_filter( 'determine_locale', function ( $locale ) use ( $lang_code ){
                if ( !empty( $lang_code ) ){
                    return $lang_code;
                }
                return $locale;
            } );
        }
        $this->page_title = __( 'My Prayer Times', 'disciple-tools-prayer-campaigns' );

        // add dt_campaign_core to allowed scripts
        add_action( 'dt_blank_head', [ $this, 'form_head' ] );
        add_action( 'dt_blank_footer', [ $this, 'form_footer' ] );

        // load if valid url
        if ( 'download_calendar' === $this->parts['action'] ) {
            $this->echo_calendar_download();
            return;
        } else if ( '' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'manage_body' ] );
        } else {
            return; // fail if no valid action url found
        }

        // load page elements
        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'dt_campaign_core';
        $allowed_js[] = 'luxon';
        $allowed_js[] = 'dt_subscription_js';
        return $allowed_js;
    }
    public function dt_magic_url_base_allowed_css( $allowed_js ) {
        $allowed_js[] = 'dt_subscription_css';
        return $allowed_js;
    }

    public function wp_enqueue_scripts(){
        wp_register_script( 'luxon', 'https://cdn.jsdelivr.net/npm/luxon@2.3.1/build/global/luxon.min.js', false, '2.3.1', true );
        wp_enqueue_script( 'dt_campaign_core', DT_Prayer_Campaigns::instance()->plugin_dir_url . 'post-type/campaign_core.js', [
            'jquery',
            'lodash',
            'luxon'
        ], filemtime( DT_Prayer_Campaigns::instance()->plugin_dir_path . 'post-type/campaign_core.js' ), true );

        $post = DT_Posts::get_post( 'subscriptions', $this->parts['post_id'], true, false );
        if ( is_wp_error( $post ) ) {
            return $post;
        }
        $campaign_id = $post['campaigns'][0]['ID'];
        $current_commitments = DT_Time_Utilities::get_current_commitments( $campaign_id, 12 );
        $my_commitments_reports = $this->get_subscriptions( $this->parts['post_id'] );
        $my_commitments = [];
        foreach ( $my_commitments_reports as $commitments_report ){
            $my_commitments[] = [
                'time_begin' => $commitments_report['time_begin'],
                'time_end' => $commitments_report['time_end'],
                'value' => $commitments_report['value'],
                'report_id' => $commitments_report['id'],
                'verified' => $commitments_report['verified'] ?? false,

            ];
        }
        $field_settings = DT_Posts::get_post_field_settings( 'campaigns' );

        wp_enqueue_style( 'dt_subscription_css', DT_Prayer_Campaigns::instance()->plugin_dir_url . 'magic-links/subscription-management/subscription-management.css', [], filemtime( DT_Prayer_Campaigns::instance()->plugin_dir_path . 'magic-links/subscription-management/subscription-management.css' ) );
        wp_enqueue_script( 'dt_subscription_js', DT_Prayer_Campaigns::instance()->plugin_dir_url . 'magic-links/subscription-management/subscription-management.js', [ 'jquery', 'dt_campaign_core' ], filemtime( DT_Prayer_Campaigns::instance()->plugin_dir_path . 'magic-links/subscription-management/subscription-management.js' ), true );
        wp_localize_script(
            'dt_subscription_js', 'jsObject', [
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'name' => get_the_title( $this->parts['post_id'] ),
                'translations' => [
                    'select_a_time' => __( 'Select a time', 'disciple-tools-prayer-campaigns' ),
                    'fully_covered_once' => __( 'fully covered once', 'disciple-tools-prayer-campaigns' ),
                    'fully_covered_x_times' => __( 'fully covered %1$s times', 'disciple-tools-prayer-campaigns' ),
                    'time_slot_label' => _x( '%1$s for %2$s minutes.', 'Monday 5pm for 15 minutes', 'disciple-tools-prayer-campaigns' ),
                    'extend_3_months' => __( 'Extend for 3 months', 'disciple-tools-prayer-campaigns' ),
                    'change_daily_time' => __( 'Change daily time', 'disciple-tools-prayer-campaigns' ),
                    'percent_covered' => _x( '%s covered', '80% covered', 'disciple-tools-prayer-campaigns' ),
                    'on_x_days' => _x( 'On %s days', 'on 5 days', 'disciple-tools-prayer-campaigns' ),
                    'and_x_more' => _x( 'and %s more', 'and 5 more', 'disciple-tools-prayer-campaigns' ),
                    'pray_this_time' => __( 'Pray this time', 'disciple-tools-prayer-campaigns' ),
                ],
                'my_commitments' => $my_commitments,
                'campaign_id' => $campaign_id,
                'current_commitments' => $current_commitments,
                'start_timestamp' => (int) DT_Time_Utilities::start_of_campaign_with_timezone( $campaign_id ),
                'end_timestamp' => (int) DT_Time_Utilities::end_of_campaign_with_timezone( $campaign_id, 12, time() ) ,
                'slot_length' => 15,
                'timezone' => $post['timezone'] ?? 'America/Chicago',
                'duration_options' => $field_settings['duration_options']['default']
            ]
        );

    }

    public function form_head(){
        wp_head(); // styles controlled by wp_print_styles and wp_print_scripts actions
    }
    public function form_footer(){
        wp_footer(); // styles controlled by wp_print_styles and wp_print_scripts actions
    }



    public function get_clean_duration( $start_time, $end_time ) {
        $time_duration = ( $start_time - $end_time ) / 60;

        switch ( true ) {
            case $time_duration < 60:
                $time_duration .= ' minutes';
                break;
            case $time_duration === 60:
                $time_duration = $time_duration / 60 . ' hour';
                break;
            case $time_duration < 60:
                $time_duration = $time_duration . ' hours';
                break;
            case $time_duration > 60:
                $time_duration = $time_duration / 60 . ' hours';
        }
        return $time_duration;
    }

    public function get_timezone_offset( $timezone ) {
        $dt_now = new DateTime();
        $dt_now->setTimezone( new DateTimeZone( esc_html( $timezone ) ) );
        $timezone_offset = sprintf( '%+03d', $dt_now->getOffset() / 3600 );
        return $timezone_offset;
    }

    public function get_download_url() {
        $download_url = trailingslashit( $this->parts['public_key'] ) .'download_calendar';
        return $download_url;
    }

    public function echo_calendar_download() {
        // Get post data
        $post = DT_Posts::get_post( 'subscriptions', $this->parts['post_id'], true, false );
        if ( is_wp_error( $post ) ) {
            return $post;
        }
        $campaign_id = $post['campaigns'][0]['ID'];
        $locale = $post['lang'] ?: 'en_US';

        //get summary from campaign strings
        $calendar_title = $post['campaigns'][0]['post_title'];
        $campaign = DT_Posts::get_post( 'campaigns', $campaign_id, true, false );
        $calendar_timezone = $post['timezone'];
        $calendar_dtstamp = gmdate( 'Ymd' ).'T'. gmdate( 'His' ) . 'Z';
        $calendar_description = '';
        if ( isset( $campaign['campaign_strings'][$locale]['reminder_content'] ) ){
            $calendar_description = $campaign['campaign_strings'][$locale]['reminder_content'];
        } elseif ( isset( $campaign['campaign_strings']['en_US']['reminder_content'] ) ){
            $calendar_description = $campaign['campaign_strings']['en_US']['reminder_content'];
        }
        $calendar_timezone_offset = self::get_timezone_offset( esc_html( $calendar_timezone ) );

        $my_commitments_reports = $this->get_subscriptions( $this->parts['post_id'] );
        $my_commitments = [];

        foreach ( $my_commitments_reports as $commitments_report ){
            $commitments_report['time_begin'] = $commitments_report['time_begin'] + $calendar_timezone_offset * 3600;
            $commitments_report['time_end'] = $commitments_report['time_end'] + $calendar_timezone_offset * 3600;

            $my_commitments[] = [
                'time_begin' => gmdate( 'Ymd', $commitments_report['time_begin'] ) . 'T'. gmdate( 'His', $commitments_report['time_begin'] ),
                'time_end' => gmdate( 'Ymd', $commitments_report['time_end'] ) . 'T'. gmdate( 'His', $commitments_report['time_end'] ),
                'time_duration' => self::get_clean_duration( $commitments_report['time_end'], $commitments_report['time_begin'] ),
                'location' => $commitments_report['label'],
            ];
        }

        header( 'Content-type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: inline; filename=calendar.ics' );

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//disciple.tools\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "BEGIN:VTIMEZONE\r\n";
        echo 'TZID:' . esc_html( $calendar_timezone ) . "\r\n";
        echo "BEGIN:STANDARD\r\n";
        echo 'TZNAME:' . esc_html( $calendar_timezone_offset ) . "\r\n";
        echo 'TZOFFSETFROM:' . esc_html( $calendar_timezone_offset ) . "00\r\n";
        echo 'TZOFFSETTO:' . esc_html( $calendar_timezone_offset ) . "00\r\n";
        echo "DTSTART:19700101T000000\r\n";
        echo "END:STANDARD\r\n";
        echo "END:VTIMEZONE\r\n";

        foreach ( $my_commitments as $mc ) {
            $calendar_uid = md5( uniqid( mt_rand(), true ) ) . '@disciple.tools';

            echo "BEGIN:VEVENT\r\n";
            echo 'UID:' . esc_html( $calendar_uid ) . "\r\n";
            echo 'DTSTAMP:' . esc_html( $calendar_dtstamp ) . "\r\n";
            echo 'SUMMARY:' . esc_html( $calendar_title ) . "\r\n";
            echo 'DTSTART:' . esc_html( $mc['time_begin'] ) . "\r\n";
            echo 'DTEND:' . esc_html( $mc['time_end'] ) . "\r\n";
            echo 'DESCRIPTION:' . esc_html( $calendar_description ) . "\r\n";
            echo "STATUS:CONFIRMED\r\n";
            echo "SEQUENCE:3\r\n";
            echo "BEGIN:VALARM\r\n";
            echo "TRIGGER:-PT10M\r\n";
            echo "ACTION:DISPLAY\r\n";
            echo "END:VALARM\r\n";
            echo "END:VEVENT\r\n";
        }

        echo "END:VCALENDAR\r\n";
        die();
    }

    public function manage_body(){
        $post = DT_Posts::get_post( 'subscriptions', $this->parts['post_id'], true, false );
        if ( !isset( $post['campaigns'][0]['ID'] ) ){
            return false;
        }
        $campaign_id = $post['campaigns'][0]['ID'];
        //cannot manage a subscription that has no campaign
        if ( empty( $campaign_id ) ){
            $this->error_body();
            exit;
        }


        $campaign = DT_Posts::get_post( 'campaigns', $campaign_id, true, false );
        if ( is_wp_error( $campaign ) ) {
            return $campaign;
        }
        ?>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell center">
                    <h2 id="title"><?php esc_html_e( 'My Prayer Times', 'disciple-tools-prayer-campaigns' ); ?></h2>
                    <i><?php echo esc_html( $post['name'] ); ?></i>
                </div>
            </div>
            <div id="times-verified-notice" style="display:none; padding: 20px; background-color: lightgreen; border-radius: 5px; border: 1px green solid; margin-bottom: 20px;">
                <?php esc_html_e( 'Your prayer times have been verified!', 'disciple-tools-prayer-campaigns' ); ?>
            </div>
            <div class="timezone-label">
                <svg height='16px' width='16px' fill="#000000" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 100 100" xml:space="preserve"><path d="M50,13c20.4,0,37,16.6,37,37S70.4,87,50,87c-20.4,0-37-16.6-37-37S29.6,13,50,13 M50,5C25.1,5,5,25.1,5,50s20.1,45,45,45  c24.9,0,45-20.1,45-45S74.9,5,50,5L50,5z"></path><path d="M77.9,47.8l-23.4-2.1L52.8,22c-0.1-1.5-1.3-2.6-2.8-2.6h-0.8c-1.5,0-2.8,1.2-2.8,2.7l-1.6,28.9c-0.1,1.3,0.4,2.5,1.2,3.4  c0.9,0.9,2,1.4,3.3,1.4h0.1l28.5-2.2c1.5-0.1,2.6-1.3,2.6-2.9C80.5,49.2,79.3,48,77.9,47.8z"></path></svg>
                <a href="javascript:void(0)" data-open="timezone-changer" class="timezone-current"></a>
            </div>
            <div id="calendar-content" class="cp-wrapper"></div>

            <!-- Reveal Modal Timezone Changer-->
            <div id="timezone-changer" class="reveal tiny" data-reveal>
                <h2><?php esc_html_e( 'Change your timezone:', 'disciple-tools-prayer-campaigns' ); ?></h2>
                <select id="timezone-select">
                    <?php
                    $selected_tz = 'America/Denver';
                    if ( !empty( $selected_tz ) ){
                        ?>
                        <option id="selected-time-zone" value="<?php echo esc_html( $selected_tz ) ?>" selected><?php echo esc_html( $selected_tz ) ?></option>
                        <option disabled>----</option>
                        <?php
                    }
                    $tzlist = DateTimeZone::listIdentifiers( DateTimeZone::ALL );
                    foreach ( $tzlist as $tz ){
                        ?><option value="<?php echo esc_html( $tz ) ?>"><?php echo esc_html( $tz ) ?></option><?php
                    }
                    ?>
                </select>
                <button class="button button-cancel clear" data-close aria-label="Close reveal" type="button">
                    <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                </button>
                <button class="button" type="button" id="confirm-timezone" data-close>
                    <?php echo esc_html__( 'Select', 'disciple-tools-prayer-campaigns' )?>
                </button>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>


            <div class="center">
                <button class="button" data-open="daily-select-modal" id="open-select-times-button" style="margin-top: 10px">
                    <?php esc_html_e( 'Add a Daily Prayer Time', 'disciple-tools-prayer-campaigns' ); ?>
                </button>
                <button class="button" data-open="select-times-modal" id="open-select-times-button" style="margin-top: 10px">
                    <?php esc_html_e( 'Add Individual Prayer Times', 'disciple-tools-prayer-campaigns' ); ?>
                </button>
                <a class="button" style="margin-top: 10px" target="_blank" href="<?php echo esc_attr( self::get_download_url() ); ?>"><?php esc_html_e( 'Download Calendar', 'disciple-tools-prayer-campaigns' ); ?></a>
            </div>
            <div style='padding: 40px; display: none' class='center' id='cp-missing-times-container'>
                <h3><?php esc_html_e( 'Help us cover these prayer times', 'disciple-tools-prayer-campaigns' ); ?></h3>
                <div id='cp-missing-time-slots'></div>
            </div>

            <h3 class="mc-title"><?php esc_html_e( 'My commitments', 'disciple-tools-prayer-campaigns' ); ?></h3>
            <div id="mobile-commitments-container">
            </div>
            <div class="reveal cp-wrapper" id="daily-select-modal" data-reveal>
                <label>
                    <strong><?php esc_html_e( 'Prayer Time', 'disciple-tools-prayer-campaigns' ); ?></strong>
                    <select id="cp-daily-time-select" class="cp-daily-time-select">
                        <option><?php esc_html_e( 'Daily Time', 'disciple-tools-prayer-campaigns' ); ?></option>
                    </select>
                </label>
                <label>
                    <strong><?php esc_html_e( 'For how long', 'disciple-tools-prayer-campaigns' ); ?></strong>
                    <select id="cp-prayer-time-duration-select" class="cp-time-duration-select"></select>
                </label>
                <p class="timezone-label">
                    <svg height='16px' width='16px' fill="#000000" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 100 100" xml:space="preserve"><path d="M50,13c20.4,0,37,16.6,37,37S70.4,87,50,87c-20.4,0-37-16.6-37-37S29.6,13,50,13 M50,5C25.1,5,5,25.1,5,50s20.1,45,45,45  c24.9,0,45-20.1,45-45S74.9,5,50,5L50,5z"></path><path d="M77.9,47.8l-23.4-2.1L52.8,22c-0.1-1.5-1.3-2.6-2.8-2.6h-0.8c-1.5,0-2.8,1.2-2.8,2.7l-1.6,28.9c-0.1,1.3,0.4,2.5,1.2,3.4  c0.9,0.9,2,1.4,3.3,1.4h0.1l28.5-2.2c1.5-0.1,2.6-1.3,2.6-2.9C80.5,49.2,79.3,48,77.9,47.8z"></path></svg>
                    <a href="javascript:void(0)" data-open="timezone-changer" class="timezone-current"></a>
                </p>

                <div class="success-confirmation-section">
                    <div class="cell center">
                        <h2><?php esc_html_e( 'Your new prayer times have been saved.', 'disciple-tools-prayer-campaigns' ); ?></h2>
                    </div>
                </div>


                <div class="center hide-on-success">
                    <button class="button button-cancel clear select-view" data-close aria-label="Close reveal" type="button">
                        <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                    </button>

                    <button disabled id="cp-confirm-daily-times" class="cp-nav button submit-form-button loader">
                        <?php esc_html_e( 'Confirm Times', 'disciple-tools-prayer-campaigns' ); ?>
                    </button>
                </div>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="center">
                    <button class="button success-confirmation-section close-ok-success" data-close aria-label="Close reveal" type="button">
                        <?php echo esc_html__( 'ok', 'disciple-tools-prayer-campaigns' )?>
                    </button>
                </div>

            </div>

            <div class="reveal" id="view-times-modal" data-reveal data-close-on-click="true">
                <h3 id="list-modal-title"></h3>

                <div id="list-day-times">
                    <table>
                        <thead>
                        <tr>
                            <th></th>
                            <th>:00</th>
                            <th>:15</th>
                            <th>:30</th>
                            <th>:45</th>
                        </tr>
                        </thead>
                        <tbody id="day-times-table-body">

                        </tbody>
                    </table>
                </div>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="center">
                    <button class="button" data-close aria-label="Close reveal" type="button">
                        <?php echo esc_html__( 'Close', 'disciple-tools-prayer-campaigns' )?>
                    </button>
                </div>
            </div>


            <div class="reveal" id="delete-time-modal" data-reveal data-close-on-click="true">
                <h3 id="delete-time-modal-title"><?php esc_html_e( 'Delete Time', 'disciple-tools-prayer-campaigns' ); ?></h3>

                <p id="delete-time-modal-text"></p>
                <p id="delete-time-extra-warning">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' ) ?>"/>
                    <?php esc_html_e( 'Need to cancel? We get it! But wait!
If your prayer commitment is scheduled to start in less than 48-hours, please ask a friend to cover it for you.
That will keep the prayer chain from being broken AND will give someone the joy of fighting for the lost! Thanks!', 'disciple-tools-prayer-campaigns' ); ?>
                </p>

                <button class='button button-cancel clear' data-close aria-label='Close reveal' type='button'>
                    <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' ) ?>
                </button>
                <button class="button loader alert" type="button" id="confirm-delete-my-time-modal">
                    <?php echo esc_html__( 'Delete', 'disciple-tools-prayer-campaigns' ) ?>
                </button>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>




            <div class="reveal cp-wrapper" id="select-times-modal" data-reveal data-close-on-click="false" data-multiple-opened="true">

                <h2 id="individual-day-title" class="cp-center">
                    <?php esc_html_e( 'Select a day and choose a time', 'disciple-tools-prayer-campaigns' ); ?>
                </h2>
                <div id="cp-day-content" class="cp-center" >
                    <div style="margin-bottom: 20px">
                        <div id="day-select-calendar" class=""></div>
                    </div>
                    <label>
                        <strong><?php esc_html_e( 'Select a prayer time', 'disciple-tools-prayer-campaigns' ); ?></strong>
                        <select id="cp-individual-time-select" disabled style="margin: auto">
                            <option><?php esc_html_e( 'Daily Time', 'disciple-tools-prayer-campaigns' ); ?></option>
                        </select>
                    </label>
                    <label>
                        <strong><?php esc_html_e( 'For how long', 'disciple-tools-prayer-campaigns' ); ?></strong>
                        <select id="cp-individual-prayer-time-duration-select" class="cp-time-duration-select" style="margin: auto"></select>
                    </label>
                    <div>
                        <button class="button" id="cp-add-prayer-time" data-day="" disabled style="margin: 10px 0; display: inline-block"><?php esc_html_e( 'Add prayer time', 'disciple-tools-prayer-campaigns' ); ?></button>
                        <span style="display: none" id="cp-time-added"><?php esc_html_e( 'Time added', 'disciple-tools-prayer-campaigns' ); ?></span>
                    </div>
                    <p class="timezone-label">
                    <svg height='16px' width='16px' fill="#000000" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 100 100" enable-background="new 0 0 100 100" xml:space="preserve"><path d="M50,13c20.4,0,37,16.6,37,37S70.4,87,50,87c-20.4,0-37-16.6-37-37S29.6,13,50,13 M50,5C25.1,5,5,25.1,5,50s20.1,45,45,45  c24.9,0,45-20.1,45-45S74.9,5,50,5L50,5z"></path><path d="M77.9,47.8l-23.4-2.1L52.8,22c-0.1-1.5-1.3-2.6-2.8-2.6h-0.8c-1.5,0-2.8,1.2-2.8,2.7l-1.6,28.9c-0.1,1.3,0.4,2.5,1.2,3.4  c0.9,0.9,2,1.4,3.3,1.4h0.1l28.5-2.2c1.5-0.1,2.6-1.3,2.6-2.9C80.5,49.2,79.3,48,77.9,47.8z"></path></svg><a href="javascript:void(0)" data-open="timezone-changer" class="timezone-current"></a>
                    </p>

                    <div style="margin: 30px 0">
                        <h3><?php esc_html_e( 'Selected Times', 'disciple-tools-prayer-campaigns' ); ?></h3>
                        <ul class="cp-display-selected-times">
                            <li><?php esc_html_e( 'No selected Time', 'disciple-tools-prayer-campaigns' ); ?></li>
                        </ul>
                    </div>

                </div>

                <div class="success-confirmation-section">
                    <div class="cell center">
                        <h2><?php esc_html_e( 'Your new prayer times have been saved.', 'disciple-tools-prayer-campaigns' ); ?></h2>
                    </div>
                </div>


                <div class="center hide-on-success">
                    <button class="button button-cancel clear select-view" data-close aria-label="Close reveal" type="button">
                        <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                    </button>

                    <button disabled id="cp-confirm-individual-times" class="button submit-form-button loader">
                        <?php esc_html_e( 'Confirm Times', 'disciple-tools-prayer-campaigns' ); ?>
                    </button>
                </div>
                <button class="button button-cancel clear confirm-view" id="back-to-select" aria-label="Close reveal" type="button">
                    <?php echo esc_html__( 'Back', 'disciple-tools-prayer-campaigns' )?>
                </button>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="center">
                    <button class="button success-confirmation-section close-ok-success" data-close aria-label="Close reveal" type="button">
                        <?php echo esc_html__( 'ok', 'disciple-tools-prayer-campaigns' )?>
                    </button>
                </div>
            </div>

            <hr>

            <table >
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Count</th>
                        <th>Change start on all prayer times</th>
                        <th>Delete prayer times</th>
                        <th>Extend Time</th>
                    </tr>
                </thead>
                <tbody id="recurring_time_slots">

                </tbody>

            </table>

            <!-- bulk time change modal -->
            <div id="change-times-modal" class="reveal tiny" data-reveal>
                <h2><?php esc_html_e( 'Choose a different time:', 'disciple-tools-prayer-campaigns' ); ?></h2>
                <select id="change-time-select" class="cp-daily-time-select">

                </select>
                <button class="button button-cancel clear" data-close aria-label="Close reveal" type="button">
                    <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                </button>
                <button class="button loader" type="button" id="update-daily-time">
                    <?php echo esc_html__( 'Select', 'disciple-tools-prayer-campaigns' )?>
                </button>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <!-- bulk time delete modal -->
            <div id="delete-times-modal" class="reveal tiny" data-reveal>
                <h2><?php esc_html_e( 'Delete all', 'disciple-tools-prayer-campaigns' ); ?></h2>

                <p id="delete-time-slot-text"></p>

                <p id='delete-time-extra-warning'>
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' ) ?>"/>
                    <?php esc_html_e( 'Need to cancel? We get it! But wait!
If your prayer commitment is scheduled to start in less than 48-hours, please ask a friend to cover it for you.
That will keep the prayer chain from being broken AND will give someone the joy of fighting for the lost! Thanks!', 'disciple-tools-prayer-campaigns' ); ?>
                </p>

                <button class="button button-cancel clear" data-close aria-label="Close reveal" type="button">
                    <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                </button>
                <button class="button loader alert" type="button" id="confirm-delete-daily-time">
                    <?php echo esc_html__( 'Delete', 'disciple-tools-prayer-campaigns' )?>
                </button>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <!-- bulk time extend modal -->
            <div id="extend-times-modal" class="reveal tiny" data-reveal>
                <h2><?php esc_html_e( 'Extend', 'disciple-tools-prayer-campaigns' ); ?></h2>

                <p><?php esc_html_e( 'Extend my prayer commitments until:', 'disciple-tools-prayer-campaigns' ); ?> <span id="extend-time-slot-text"></span></p>
                <button class="button button-cancel clear" data-close aria-label="Close reveal" type="button">
                    <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                </button>
                <button class="button loader submit-form-button" type="button" id="confirm-extend-daily-time">
                    <?php echo esc_html__( 'Confirm', 'disciple-tools-prayer-campaigns' )?>
                </button>

                <button class="close-button" data-close aria-label="Close modal" type="button">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Extra setting depending on the campaign type -->
            <div>
                <?php do_action( 'dt_subscription_management_extra' ) ?>
            </div>

            <?php
                $notifications = isset( $post['receive_prayer_time_notifications'] ) && !empty( $post['receive_prayer_time_notifications'] );
            ?>

            <div style="margin-top: 50px">
                <hr>
                <h2><?php esc_html_e( 'Profile Settings', 'disciple-tools-prayer-campaigns' ); ?></h2>
                <div><?php esc_html_e( 'Receive prayer time notifications', 'disciple-tools-prayer-campaigns' ); ?> <span class="notifications_allowed_spinner loading-spinner"></span>
                    <select name="allow_notifications" id="allow_notifications">
                        <option <?php selected( $notifications ) ?> value="allowed"><?php esc_html_e( 'Notifications allowed', 'disciple-tools-prayer-campaigns' ); ?> ✅</option>
                        <option <?php selected( !$notifications ) ?> value="disallowed"><?php esc_html_e( 'Notifications not allowed', 'disciple-tools-prayer-campaigns' ); ?> ❌</option>
                    </select>
                </div>
                <div class="danger-zone">
                    <h2><?php esc_html_e( 'Advanced Settings', 'disciple-tools-prayer-campaigns' ); ?></h2>
                    <button class="chevron" onclick="toggle_danger();">
                        <img src="<?php echo esc_html( get_template_directory_uri() ); ?>/dt-assets/images/chevron_down.svg">
                    </button>
                </div>
                <div class="danger-zone-content collapsed">
                    <label>
                        <?php esc_html_e( 'Delete this profile and all the scheduled prayer times?', 'disciple-tools-prayer-campaigns' ); ?>
                    </label>
                    <button class="button alert" data-open="delete-profile-modal"><?php esc_html_e( 'Delete', 'disciple-tools-prayer-campaigns' ); ?></button>
                    <!-- Reveal Modal Daily time slot-->
                    <div id="delete-profile-modal" class="reveal tiny" data-reveal>
                        <h2><?php esc_html_e( 'Are you sure you want to delete your profile?', 'disciple-tools-prayer-campaigns' ); ?></h2>
                        <p>
                            <?php esc_html_e( 'This can not be undone.', 'disciple-tools-prayer-campaigns' ); ?>
                        </p>
                        <p id='delete-time-extra-warning'>
                            <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' ) ?>"/>
                            <?php esc_html_e( 'Need to cancel? We get it! But wait!
If your prayer commitment is scheduled to start in less than 48-hours, please ask a friend to cover it for you.
That will keep the prayer chain from being broken AND will give someone the joy of fighting for the lost! Thanks!', 'disciple-tools-prayer-campaigns' ); ?>
                        </p>
                        <p id="delete-account-errors"></p>


                        <button class="button button-cancel clear" data-close aria-label="Close reveal" type="button">
                            <?php echo esc_html__( 'Cancel', 'disciple-tools-prayer-campaigns' )?>
                        </button>
                        <button class="button loader alert" type="button" id="confirm-delete-profile">
                            <?php echo esc_html__( 'Delete', 'disciple-tools-prayer-campaigns' )?>
                        </button>

                        <button class="close-button" data-close aria-label="Close modal" type="button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function error_body(){
        ?>
        <div class="center" style="margin-top:50px">
            <h2 class=""><?php esc_html_e( 'This subscription has ended or is not configured correctly.', 'disciple-tools-prayer-campaigns' ); ?></h2>
        </div>
        <?php
    }

    public function add_api_routes() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/'.$this->type, [
                [
                    'methods'  => 'POST',
                    'callback' => [ $this, 'manage_profile' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->type . '/delete_profile', [
                [
                    'methods'  => 'DELETE',
                    'callback' => [ $this, 'delete_profile' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->type . '/allow-notifications', [
                [
                    'methods'  => 'POST',
                    'callback' => [ $this, 'allow_notifications' ],
                    'permission_callback' => function( WP_REST_Request $request ){
                        $magic = new DT_Magic_URL( $this->root );
                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
    }

    public function delete_profile( WP_REST_Request $request ){
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['parts']['meta_key'], $params['parts']['public_key'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }
        $params = dt_recursive_sanitize_array( $params );

        $post_id = $params['parts']['post_id']; //has been verified in verify_rest_endpoint_permissions_on_post()
        if ( ! $post_id ){
            return new WP_Error( __METHOD__, 'Missing post record', [ 'status' => 400 ] );
        }
        global $wpdb;

        //remove connection
        $a = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts p SET post_title = 'Deleted Subscription', post_name = 'Deleted Subscription' WHERE p.ID = %s", $post_id ) );
        $a = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->dt_activity_log WHERE object_id = %s and action != 'add_subscription' and action !='delete_subscription'", $post_id ) );
        //create activity for connection removed on the campaign
//        DT_Posts::update_post( 'subscriptions', $post_id, [ 'campaigns' => [ 'values' => [], 'force_values' => true ] ], true, false );
        $a = $wpdb->query( $wpdb->prepare( "DELETE pm FROM $wpdb->postmeta pm WHERE pm.post_id = %s", $post_id ) );
        $a = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->dt_reports WHERE post_id = %s AND time_begin > %d", $post_id, time() ) );
        DT_Posts::update_post( 'subscriptions', $post_id, [ 'status' => 'inactive' ], true, false );

        $base_user = dt_get_base_user();
        DT_Posts::add_post_comment( 'subscriptions', $post_id, "@[$base_user->display_name]($base_user->ID) A user has deleted their profile and future prayer times.", 'comment', [], false, false );

        return true;
    }

    public function manage_profile( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['parts']['meta_key'], $params['parts']['public_key'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );
        $action = sanitize_text_field( wp_unslash( $params['action'] ) );

        // manage
        $post_id = $params['parts']['post_id']; //has been verified in verify_rest_endpoint_permissions_on_post()
        if ( ! $post_id ){
            return new WP_Error( __METHOD__, 'Missing post record', [ 'status' => 400 ] );
        }

        switch ( $action ) {
            case 'get':
                return $this->get_subscriptions( $post_id );
            case 'delete':
                return $this->delete_subscription_endpoint( $post_id, $params );
            case 'add':
                return $this->add_subscriptions( $post_id, $params );
            case 'change_times':
                return $this->change_times( $post_id, $params );
            case 'delete_times':
                return $this->delete_times( $post_id, $params );
            default:
                return new WP_Error( __METHOD__, 'Missing valid action', [ 'status' => 400 ] );
        }
    }

    public function get_subscriptions( $post_id ) {
        $subs = Disciple_Tools_Reports::get( $post_id, 'post_id' );

        if ( ! empty( $subs ) ){
            foreach ( $subs as $index => $sub ) {
                // verification step
                if ( $sub['value'] < 1 ) {
                    Disciple_Tools_Reports::update( [
                        'id' => $sub['id'],
                        'value' => 1
                    ] );
                    $subs[$index]['value'] = 1;
                    $subs[$index]['verified'] = true;
                }

                $subs[$index]['formatted_time'] = gmdate( 'F d, Y @ H:i a', $sub['time_begin'] ) . ' for ' . $sub['label'];
            }
        }

        return $subs;
    }

    private function delete_subscription( $post_id, $report_id ){
        $sub = Disciple_Tools_Reports::get( $report_id, 'id' );
        if ( empty( $sub ) ){
            return new WP_Error( __METHOD__, 'Missing subscription record', [ 'status' => 400 ] );
        }

        if ( $sub['time_begin'] < time() ){
            return new WP_Error( __METHOD__, 'Cannot delete a commitment that is the past', [ 'status' => 400 ] );
        }
        $time_in_mins = ( $sub['time_end'] - $sub['time_begin'] ) / 60;
        $label = 'Commitment deleted: ' . gmdate( 'F d, Y @ H:i a', $sub['time_begin'] ) . ' UTC for ' . $time_in_mins . ' minutes';
        Disciple_Tools_Reports::delete( $report_id );
        dt_activity_insert([
            'action' => 'delete_subscription',
            'object_type' => $this->post_type,
            'object_subtype' => 'report',
            'object_note' => $label,
            'object_id' => $post_id
        ] );
        $subscription = DT_Posts::get_post( 'subscriptions', $post_id, true, false );
        $campaign_id = $subscription['campaigns'][0]['ID'] ?? null;
        if ( $campaign_id ){
            $campaign = DT_Posts::get_post( 'campaigns', $campaign_id, true, false );
            $start_date = $campaign['start_date']['timestamp'] ?? null;
            if ( $sub['time_begin'] < $start_date + 2 * DAY_IN_SECONDS ){
                $base_user = dt_get_base_user();
                DT_Posts::add_post_comment( 'subscriptions', $post_id, "@[$base_user->display_name]($base_user->ID) A prayer time close to the campaign start has been deleted", 'comment', [], false, false );
            }
        }
        do_action( 'subscriptions_removed', $campaign_id, $post_id );
        return true;
    }

    private function delete_subscription_endpoint( $post_id, $params ) {
        $this->delete_subscription( $post_id, $params['report_id'] );
        return $this->get_subscriptions( $post_id );
    }

    private function add_subscriptions( $post_id, $params ){
        $post = DT_Posts::get_post( 'subscriptions', $post_id, true, false );
        if ( !isset( $post['campaigns'][0]['ID'] ) ){
            return false;
        }
        $campaign_id = $post['campaigns'][0]['ID'];

        foreach ( $params['selected_times'] as $time ){
            if ( !isset( $time['time'] ) ){
                continue;
            }
            $new_report = DT_Subscriptions::add_subscriber_time( $campaign_id, $post_id, $time['time'], $time['duration'], $time['grid_id'] ?? null, true );
            if ( !$new_report ){
                return new WP_Error( __METHOD__, 'Sorry, Something went wrong', [ 'status' => 400 ] );
            }
        }
        do_action( 'subscriptions_added', $campaign_id, $post_id );
        return $this->get_subscriptions( $params['parts']['post_id'] );
    }

    private function change_times( $post_id, $params ){
        $post = DT_Posts::get_post( 'subscriptions', $post_id, true, false );
        if ( !isset( $post['campaigns'][0]['ID'] ) ){
            return false;
        }
        if ( !isset( $params['offset'] ) ){
            return false;
        }

        $ids = dt_array_to_sql( $params['report_ids'] );
        global $wpdb;
        //phpcs:disable.
        //Cannot pass in array in prepare
        $wpdb->query( $wpdb->prepare( "
            UPDATE $wpdb->dt_reports
            SET time_begin = time_begin + %d, time_end = time_end + %d
            WHERE id IN ($ids)
        ", (int) $params["offset"], (int) $params["offset"] ) );
        //phpcs:enable

        return $this->get_subscriptions( $params['parts']['post_id'] );
    }
    private function delete_times( $post_id, $params ){
        $post = DT_Posts::get_post( 'subscriptions', $post_id, true, false );
        $campaign_id = $post['campaigns'][0]['ID'] ?? null;
        if ( $campaign_id ){
            $campaign = DT_Posts::get_post( 'campaigns', $campaign_id, true, false );
            $start_date = $campaign['start_date']['timestamp'] ?? null;
            if ( time() > $start_date - 3 * DAY_IN_SECONDS && time() < $start_date + 3 * DAY_IN_SECONDS ){
                $base_user = dt_get_base_user();
                DT_Posts::add_post_comment( 'subscriptions', $post_id, "@[$base_user->display_name]($base_user->ID) Reoccurring prayer time deleted", 'comment', [], false, false );
            }
        }
        if ( !isset( $post['campaigns'][0]['ID'] ) ){
            return false;
        }
        foreach ( $params['report_ids'] as $id ){
            $this->delete_subscription( $post['ID'], $id );
        }
        do_action( 'subscriptions_removed', $campaign_id, $post_id );
        return $this->get_subscriptions( $params['parts']['post_id'] );
    }


    public function allow_notifications( WP_REST_Request $request ){
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['parts']['meta_key'], $params['parts']['public_key'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }
        $params = dt_recursive_sanitize_array( $params );

        $post_id = $params['parts']['post_id']; //has been verified in verify_rest_endpoint_permissions_on_post()
        if ( ! $post_id ){
            return new WP_Error( __METHOD__, 'Missing post record', [ 'status' => 400 ] );
        }

        return update_post_meta( $post_id, 'receive_prayer_time_notifications', !empty( $params['allowed'] ) );

    }
}
new DT_Prayer_Subscription_Management_Magic_Link();
