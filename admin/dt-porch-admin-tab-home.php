<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

class DT_Porch_Admin_Tab_Home extends DT_Porch_Admin_Tab_Base {

    public $title = 'Landing Page Settings';

    public $key = 'settings';
    private $languages_manager;

    public function __construct( string $porch_dir ) {
        parent::__construct( $this->key, $porch_dir );
        $this->languages_manager = new DT_Campaign_Languages();
    }

    public function body_content() {

        $this->process_language_settings();
        $this->process_new_language();

        $this->main_column();
        $this->box_languages();

    }

    private function box_languages() {
            $languages = $this->languages_manager->get();
        ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Language Settings</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2">
                        <p>
                            These are the languages with existing translations, where the landing page and emails are translated. <br>
                            Please help us and new translations and improve existing ones. See <a href="https://pray4movement.org/docs/translation/" target="_blank">translation</a>.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="language_settings_nonce" id="language_settings_nonce"
                                    value="<?php echo esc_attr( wp_create_nonce( 'language_settings' ) ) ?>"/>

                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Flag</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $enabled_column = array_column( $languages, 'enabled' );
                                        array_multisort( $enabled_column, SORT_DESC, $languages );
                                    ?>

                                    <style>
                                        .disabled-language {
                                            background-color: darkgrey;
                                        }
                                        .widefat .disabled-language td {
                                            color: white;
                                        }
                                    </style>

                                    <?php foreach ( $languages as $code => $language ): ?>

                                        <tr class="<?php echo $language['enabled'] === false ? 'disabled-language' : '' ?>">
                                            <td><?php echo esc_html( $language['english_name'] ) ?></td>
                                            <td><?php echo esc_html( $code ) ?></td>
                                            <td><?php echo esc_html( $language['flag'] ) ?></td>
                                            <td>

                                                <?php if ( isset( $language['enabled'] ) && $language['enabled'] === true ): ?>

                                                <button class="button" name="language_settings_disable" value="<?php echo esc_html( $code ) ?>">
                                                    Disable
                                                </button>

                                                <?php else : ?>

                                                <button class="button button-primary" name="language_settings_enable" value="<?php echo esc_html( $code ) ?>">
                                                    Enable
                                                </button>

                                                <?php endif; ?>

                                                <?php if ( !isset( $language['default'] ) || $language['default'] !== true ): ?>

                                                <button class="button" name="language_settings_remove" value="<?php echo esc_html( $code ) ?>">
                                                    Remove
                                                </button>

                                                <?php endif; ?>

                                            </td>
                                        </tr>

                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td>
                        Add a new untranslated language (usually used to create prayer fuel):
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="add_language_nonce" id="add_language_nonce"
                                    value="<?php echo esc_attr( wp_create_nonce( 'add_language' ) ) ?>"/>

                            <?php $language_list = $this->languages_manager->language_list() ?>

                            <select name="new_language" id="language_list">

                            <?php foreach ( $language_list as $code => $language ): ?>

                                <option value="<?php echo esc_html( $code ) ?>"><?php echo esc_html( $language['flag'] . ' ' . $language['label'] ) ?></option>

                            <?php endforeach; ?>

                            </select>
                            <button class="button">
                                Add
                            </button>
                        </form>
                    </td>
                </tr>
            </tbody>
        </table>
        <br/>

        <?php
    }

    /**
     * Process changes to the language settings
     */
    public function process_language_settings() {
        if ( isset( $_POST['language_settings_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['language_settings_nonce'] ) ), 'language_settings' ) ) {

            if ( isset( $_POST['language_settings_disable'] ) ) {
                $this->languages_manager->disable( DT_Prayer_Campaigns_Campaigns::sanitized_post_field( $_POST, 'language_settings_disable' ) );
            }

            if ( isset( $_POST['language_settings_enable'] ) ) {
                $this->languages_manager->enable( DT_Prayer_Campaigns_Campaigns::sanitized_post_field( $_POST, 'language_settings_enable' ) );
            }

            if ( isset( $_POST['language_settings_remove'] ) ) {
                $this->languages_manager->remove( DT_Prayer_Campaigns_Campaigns::sanitized_post_field( $_POST, 'language_settings_remove' ) );
            }
        }
    }

    /**
     * Process changes to the language settings
     */
    public function process_new_language() {
        if ( isset( $_POST['add_language_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['add_language_nonce'] ) ), 'add_language' ) ) {
            if ( isset( $_POST['new_language'] ) ) {
                $this->languages_manager->add_from_code( DT_Prayer_Campaigns_Campaigns::sanitized_post_field( $_POST, 'new_language' ) );
            }
        }
    }


}
