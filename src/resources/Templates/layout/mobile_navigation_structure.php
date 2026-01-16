<?php
/* 
 * Travian-style Mobile Navigation Structure
 * This replaces the navigation section in Layout.php (lines ~20-200)
 */
?>
    <div id="topBar">
        <div id="header">
            <input type="checkbox" id="mobileMenuState">
            
            <div id="navigation">
                <a class="village resourceView <?=$vars['bodyCssClass'] == 'perspectiveBuildings' ? 'active' : ''; ?>" 
                   href="dorf1.php" 
                   accesskey="1"
                   title="<?=T("inGame", "Navigation.Resources"); ?>||"></a>
                   
                <a class="village buildingView <?=$vars['bodyCssClass'] == 'perspectiveResources' ? 'active' : ''; ?>" 
                   href="dorf2.php" 
                   accesskey="2"
                   title="<?=T("inGame", "Navigation.Buildings"); ?>||"></a>
                   
                <a class="map" 
                   href="karte.php" 
                   accesskey="3"
                   title="<?=T("inGame", "Navigation.Map"); ?>||"></a>
                   
                <a class="statistics" 
                   href="statistiken.php" 
                   accesskey="4"
                   title="<?=T("inGame", "Navigation.Statistics"); ?>||"></a>
                   
                <a class="reports" 
                   href="reports.php" 
                   accesskey="5"
                   title="<?=T("inGame", "Navigation.Reports"); ?>||<?=T("inGame", "Navigation.newReports"); ?>: <?=$vars['newReportsCount']; ?>">
                    <?php if ($vars['newReportsCount']): ?>
                        <div class="indicator"><?=$vars['newReportsCount'] > 99 ? '99+' : $vars['newReportsCount']; ?></div>
                    <?php endif; ?>
                </a>
                
                <a class="messages" 
                   href="messages.php" 
                   accesskey="6"
                   title="<?=T("inGame", "Navigation.Messages"); ?>||<?=T("inGame", "Navigation.newMessages"); ?>: <?=$vars['newMessagesCount']; ?>">
                    <?php if ($vars['newMessagesCount']): ?>
                        <div class="indicator"><?=$vars['newMessagesCount'] > 99 ? '99+' : $vars['newMessagesCount']; ?></div>
                    <?php endif; ?>
                </a>
                
                <label class="mobileMenuButton" for="mobileMenuState"></label>
                <a class="mobileShopButton" 
                   href="#" 
                   accesskey="8"
                   onclick="jQuery(window).trigger('startPaymentWizard', {}); this.blur(); return false;"></a>
            </div>

            <div class="currency">
                <div class="gold">
                    <img src="img/x.gif" 
                         alt="<?=T("inGame", "gold"); ?>"
                         title="<?=T("inGame", "gold"); ?>"
                         class="gold"
                         onclick="jQuery(window).trigger('startPaymentWizard', {data:{activeTab: 'pros'}}); return false;"/>
                    <span class="ajaxReplaceableGoldAmount">
                        <?php if (getCustom("serverIsFreeGold")): ?>
                            <b><?= T("Global", "Unlimited"); ?></b>
                        <?php else: ?>
                            <?=$vars['goldCount']; ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="silver">
                    <img src="img/x.gif" 
                         alt="<?=T("inGame", "silver"); ?>"
                         title="<?=T("inGame", "silver"); ?>"
                         class="silver"
                         onclick="jQuery(window).trigger('startPaymentWizard', {data:{activeTab: 'pros'}}); return false;"/>
                    <span class="ajaxReplaceableSilverAmount"><?=$vars['silverCount']; ?></span>
                </div>
            </div>

            <nav id="mobileMenu">
                <ul>
                    <li>
                        <a class="statistics" href="statistiken.php">
                            <span class="value"><?=T("inGame", "Navigation.Statistics"); ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="profile" href="spieler.php">
                            <span class="value"><?=T("inGame", "Profile.Profile"); ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="options" href="options.php">
                            <span class="value"><?=T("inGame", "Options.Options"); ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="help" href="help.php">
                            <span class="value"><?=T("inGame", "Help.Help"); ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="logout" href="logout.php">
                            <span class="value"><?=T("inGame", "Logout.Logout"); ?></span>
                        </a>
                    </li>
                </ul>
            </nav>

            <nav id="outOfGame">
                <!-- Desktop-only out of game buttons -->
            </nav>
        </div>
    </div>
