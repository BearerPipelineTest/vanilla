<?php if (!defined('APPLICATION')) {exit();}
/**
 * @author Isis Graziatto <isis.g@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

class KeystoneThemeHooks extends Gdn_Plugin {

    /**
     * Run once on enable.
     *
     * @return void
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Run on utility/update.
     *
     * @return void
     */
    public function structure() {
        saveToConfig([
            'Garden.MobileTheme' => 'keystone',
            'Badges.BadgesModule.Target' => 'AfterUserInfo',
            'Garden.ThemeOptions.Styles.Key' => 'Default',
            'Garden.ThemeOptions.Styles.Value' => '%s_default',
            'Garden.ThemeOptions.Options.hasHeroBanner' => false,
            'Garden.ThemeOptions.Options.hasFeatureSearchbox' => false,
            'Garden.ThemeOptions.Options.panelToLeft' => false,
        ]);
    }

    /**
     * Runs every page load
     *
     *
     * @param Gdn_Controller $sender This could be any controller
     *
     * @return void
     */
    public function base_render_before($sender) {
        if (inSection('Dashboard')) {
            return;
        }

        // Set Data "heroImageUrl" to smarty
        if (class_exists('HeroImagePlugin')) {
            $imageUrl = HeroImagePlugin::getCurrentHeroImageLink();
            $sender->setData('heroImageUrl', $imageUrl);
        }else{
            //unset config ThemeOptions.Options referent the HeroImagePlugin
            saveToConfig([
                'Garden.ThemeOptions.Options.hasHeroBanner' => false,
                'Garden.ThemeOptions.Options.hasFeatureSearchbox' => false,
            ]);
        }

        $hasAdvancedSearch = class_exists('AdvancedSearchPlugin');

        //set "hasAdvancedSearch" to smarty
        $sender->setData('hasAdvancedSearch', $hasAdvancedSearch);

        //unset config ThemeOptions.Options.hasFeatureSearchbox if AdvancedSearchPlugin is disabled
        if(!$hasAdvancedSearch) {
            saveToConfig([
                'Garden.ThemeOptions.Options.hasFeatureSearchbox' => false,
            ]);
        }

        //set ThemeOptions to smarty
        $themeOptions = c("Garden.ThemeOptions");

        foreach ($themeOptions as $key => &$value) {
            $sender->setData("ThemeOptions.".$key, $value);
        }
    }

    /**
     * Register {searchbox_advanced} even if AdvancedSearchPlugin is disabled so theme doens't break
     * @param Smarty $sender
     * @param type $args
     */
    public function gdn_smarty_init_handler($sender, $args) {
        if(!class_exists('AdvancedSearchPlugin')) {
            $sender->register_function('searchbox_advanced', 'searchBoxAdvancedMock');
        }
    }

    /**
     * Overwrites method to support `hasHeroBanner`, `hasFeatureSearchbox` and `panelToLeft` custom fields
     *
     * @param SettingsController $sender
     */
    public function settingsController_themeOptions_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $form = $sender->Form;

        $sender->addJsFile('addons.js');
        $sender->setHighlightRoute('dashboard/settings/themeoptions');

        $themeManager = Gdn::themeManager();
        //$themeManager = Gdn::addonManager()->getTheme();
        $sender->setData('ThemeInfo', $themeManager->enabledThemeInfo());

        // set hasHeroImagePlugin to view
        $sender->setData('hasHeroImagePlugin', class_exists('HeroImagePlugin'));
        $sender->setData('hasAdvancedSearch', class_exists('AdvancedSearchPlugin'));

        //get toggle values from config
        $checkboxes = c("Garden.ThemeOptions.Options");

        foreach ($checkboxes  as $key => $value) {
            $form->setValue("ThemeOptions.Options.".$key, $value);
        }

        if ($form->authenticatedPostBack()) {
            // Save the styles to the config.
            $styleKey = $form->getFormValue('StyleKey');

            $configSaveData = [
                'Garden.ThemeOptions.Styles.Key' => $styleKey,
                'Garden.ThemeOptions.Styles.Value' => $sender->data("ThemeInfo.Options.Styles.$styleKey.Basename")];

            // Save the text to the locale.
            $translations = [];
            foreach ($sender->data('ThemeInfo.Options.Text', []) as $key => $default) {
                $value = $form->getFormValue($form->escapeFieldName('Text_'.$key));
                $configSaveData["ThemeOption.{$key}"] = $value;
                //$form->setFormValue('Text_'.$Key, $Value);
            }

            foreach ($form->_FormValues["Checkboxes"]  as $key => $fieldName) {
                $value = $form->getFormValue($fieldName) === false ? false : true;
                $configSaveData["Garden.{$fieldName}"] = $value;
            }

            saveToConfig($configSaveData);
            $sender->informMessage(t("Your changes have been saved."));
        }

        $sender->setData('ThemeOptions', c('Garden.ThemeOptions'));
        $styleKey = $sender->data('ThemeOptions.Styles.Key');

        if (!$form->isPostBack()) {
            foreach ($sender->data('ThemeInfo.Options.Text', []) as $key => $options) {
                $default = val('Default', $options, '');
                $value = c("ThemeOption.{$key}", '#DEFAULT#');
                if ($value === '#DEFAULT#') {
                    $value = $default;
                }

                $form->setValue($form->escapeFieldName('Text_'.$key), $value);
            }
        }

        $sender->setData('ThemeFolder', $themeManager->getEnabledDesktopThemeKey());
        $sender->title(t('Theme Options'));
        $form->addHidden('StyleKey', $styleKey);

        $sender->render();
    }
}

if (!function_exists('searchBoxAdvancedMock')):

    function searchBoxAdvancedMock($params) {
        return "";
    }

endif;
