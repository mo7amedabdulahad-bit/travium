<div id="topBarHero" class="heroV2">
    <!-- Health Progress -->
    <svg class="health" viewBox="0 0 110 110">
        <circle cx="55" cy="55" r="50" fill="none" stroke="#e0e0e0" stroke-width="10"/>
        <circle cx="55" cy="55" r="50" fill="none" stroke="<?= ($vars['regenerating'] ? $vars['regeneratedHealth'] : $vars['health']) < 25 ? '#FF0000' : '#88CC00'; ?>" stroke-width="10" 
                stroke-dasharray="<?= (($vars['regenerating'] ? $vars['regeneratedHealth'] : $vars['health'])/100) * 314; ?> 314" transform="rotate(-90 55 55)"/>
    </svg>
    
    <!-- Experience Progress -->
    <svg class="experience" viewBox="0 0 110 110">
        <circle cx="55" cy="55" r="50" fill="none" stroke="#e0e0e0" stroke-width="10"/>
        <circle cx="55" cy="55" r="50" fill="none" stroke="#0000FF" stroke-width="10" 
                stroke-dasharray="<?= ($vars['expPercent']/100) * 314; ?> 314" transform="rotate(-90 55 55)"/>
    </svg>

    <!-- Hero Image Button -->
    <a id="heroImageButton" href="hero.php<?=$vars['hasNewPoints'] ? "?flagAttributesBoxOpen" : ''; ?>" class="heroImageButton" type="button" title="<?=htmlspecialchars($vars['longStatus']); ?> - <?= $vars['playerName']; ?>">
        <div class="heroImageHover">
            <img class="heroImage" src="hero_head.php?uid=<?=$vars['uid']; ?>&amp;size=sideinfo&amp;<?=$vars['heroImageHash']; ?>" alt="Hero">
        </div>
    </a>
    
    <!-- Status Icon -->
    <div class="heroStatus">
        <img alt="<?=$vars['shortStatus']; ?>" title="<?=htmlspecialchars($vars['longStatus']); ?>" src="img/x.gif" class="heroStatus<?=$vars['status']; ?>"/>
    </div>
    
    <?php if ($vars['dead']): ?>
        <i class="dead"></i>
    <?php elseif ($vars['lvlUp']): ?>
        <i class="levelUp"></i>
    <?php endif; ?>
    
    <!-- Auction Button -->
    <a id="<?=$vars['auctionWhiteButton']['id']; ?>" class="layoutButton buttonFramed withIcon round auction green" href="hero.php?t=4" title="<?=T("HeroGlobal", "Auctions"); ?>">
        <div class="button-container"><i></i></div>
    </a>
    
    <!-- Adventure Button -->
    <a id="<?=$vars['adventureWhiteButton']['id']; ?>" class="layoutButton buttonFramed withIcon round adventure green <?= $vars['adventureWhiteButton']['adventureCount'] ? 'attention' : ''; ?>" href="hero.php?t=3" title="<?=T("HeroGlobal", "Adventure"); ?>">
        <div class="button-container"><i></i></div>
        <?php if ($vars['adventureWhiteButton']['adventureCount']): ?>
            <div class="content">&nbsp;<?=$vars['adventureWhiteButton']['adventureCount']; ?></div>
        <?php endif; ?>
    </a>
</div>