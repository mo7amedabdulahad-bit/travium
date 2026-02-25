<?php

use Core\Config;
use Core\Helper\TimezoneHelper;

?>
<?php require __DIR__ . "/head.php"; ?>
<body data-theme="<?= detect_season() === 'winter' ? 'winter' : 'default'; ?>" data-village-perspective="<?=$vars['bodyCssClass']; ?>" class="v35 webkit chrome <?=get_locale();?> <?= Config::getProperty("settings", "global_css_class"); ?> <?=$vars['contentCssClass']; ?> <?= $vars['colorBlind'] ? 'colorBlind' : ''; ?> <?=$vars['bodyCssClass']; ?> <?= (getDirection() == 'RTL' ? 'rtl' : 'ltr'); ?> season-<?= detect_season(); ?> buildingsV1">


<div id="reactDialogWrapper"></div>
<style>
/* Override T4.4 legacy defaults for T4.6 map */
#content.village1 { background: transparent !important; }
#contentOuterContainer { background: transparent !important; }
#resourceFieldContainer { top: 220px !important; margin-top: 0 !important; }
.notNow .level { filter: grayscale(100%); }
@media (min-width: 768px) {
    #mobileMenu { display: none !important; }
}
</style>
<div id="background">
    <?php
    if ($vars['headerBar']) {
        echo '<div id="headerBar"></div>';
    }
    ?>
    <div id="center">
        <div id="sidebarBeforeContent" class="sidebar beforeContent">
                <?=$vars['sidebarBeforeContent']; ?>
                <div class="clear"></div>
            </div>
            <div id="contentOuterContainer" class="size1">

                <div class="contentTitle">
                    <?php if ($vars['showCloseButton']): ?>
                        <a id="closeContentButton" class="contentTitleButton"
                           href="<?=$vars['bodyCssClass'] == 'perspectiveResources' ? 'dorf1.php' : 'dorf2.php'; ?>"
                           title="<?=T("Global", "General.closeWindow"); ?>">
                            &nbsp;</a>
                    <?php endif; ?>
                    <?php if ($vars['answerId']): ?>
                        <a id="answersButton" class="contentTitleButton"
                           href="<?=getAnswersUrl(); ?>aid=<?=$vars['answerId']; ?>#go2answer"
                           target="_blank"
                           title="<?=T("Global", "FAQ"); ?>">&nbsp;</a>
                    <?php endif; ?>
                </div>
                <div id="contentOuterContainer" class="contentContainer">
                    <div id="content"
                         class="<?=$vars['contentCssClass']; ?>">
                        <?php
                        if ($vars['titleInHeader']) {
                            echo '<h1 class="titleInHeader">' . $vars['titleInHeader'] . '</h1>';
                        }
                        echo $vars['content'];
                        ?>
                        <div class="clear"></div>
                    </div>
                    <div class="clear">&nbsp;</div>
                </div>
                <div class="contentFooter"></div>
            </div>
            <div id="sidebarAfterContent" class="sidebar afterContent">
                <?=$vars['sidebarAfterContent']; ?>
                <div class="clear"></div>
            </div>

            <div class="clear"></div>
        </div>
        <div id="footer">
            <!--email_off-->
            <div id="pageLinks">
                <a href="<?=Config::getInstance()->settings->indexUrl; ?>"
                   target="_blank"><?=T("Global", "Footer.HomePage"); ?></a>
                <a href="<?=getForumUrl(); ?>"
                   target="_blank"><?=T("Global", "Footer.Forum"); ?></a>
                <a href="<?=Config::getInstance()->settings->indexUrl; ?>links.php"
                   target="_blank"><?=T("Global", "Footer.Links"); ?></a>
                <a href="<?=getAnswersUrl(); ?>"
                   target="_blank"><?=T("Global", "Footer.FAQ"); ?></a>
                <a href="<?=Config::getInstance()->settings->indexUrl; ?>agb.php"
                   target="_blank"><?=T("Global", "Footer.Terms"); ?></a>
                <a href="<?=Config::getInstance()->settings->indexUrl; ?>impressum.php"
                   target="_blank"><?=T("Global", "Footer.Imprint"); ?></a>
                <div class="clear"></div>
            </div>
            <br/>
            <p class="copyright" style="direction:ltr;">© 2011 - <?=date("Y"); ?> Travian Games GmbH</p>
            <!--/email_off-->
            <br/>
        </div>
        <?php
        if ($vars['headerBar']) {
            if (!isset($vars['dateTime'])) {
                $vars['dateTime'] = time();
            }
            ?>
            <div id="servertime" class="stime">
                <?=T("inGame", "serverTime"); ?>:&nbsp;
                <?=appendTimer($vars['dateTime'], 1); ?>
            </div>
        <?php } ?>
    </div> <!-- closes background -->
    <div id="ce"></div>
</div>
<script type="text/javascript">
    <?php
    $feature_flags = [
        'vacationMode'            => true,
        "territory"               => false,
        "heroitems"               => true,
        "allianceBonus"           => true,
        "boostedStart"            => false,
        "pushingProtectionAlways" => false,
        "tribesEgyptiansAndHuns"  => false,
        "hideFoolsArtifacts"      => false,
        "welcomeScreen"           => false
    ];
    ?>
    var T4_feature_flags = <?=json_encode($feature_flags);?>
</script>
</body>
</html>
<!---- This page was generated in <?= round(1000 * (microtime(true) - $GLOBALS['start_time']), 2); ?> ms ---->
