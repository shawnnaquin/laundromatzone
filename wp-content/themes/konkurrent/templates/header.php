<header id="masthead" class="site-header" role="banner">
    <div class="site-branding col-xs-9 col-sm-4">
        <?php the_custom_logo(); ?>
        <?php if(get_header_textcolor() == 'blank') { 
            $header_class = 'hidden';
        } else {
            $header_class = '';
        } ?>
            <div class="site-branding-text <?php echo esc_attr($header_class); ?>">
                <h1 class="site-title"><a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo get_bloginfo( 'name' ); ?></a></h1>
                <?php $description = get_bloginfo( 'description', 'display' );
                    if ( $description || is_customize_preview() ) : ?>
                        <p class="site-description"><?php echo $description; ?></p>
                <?php endif; ?>
            </div>
    </div>
    
    <?php if ( has_nav_menu( 'primary_navigation' ) ) { ?>
    
            <div class="primary-navigation pull-right">
                <nav class="nav-primary" role="navigation">
                    <div class="navbar-header">
                        <button type="button" class="navbar-toggle collapsed" data-target="#header-menu" data-toggle="collapse">
                            <span class="sr-only"><?php _e('Toggle navigation', 'konkurrent'); ?></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </button>
                    </div>
                    <div id="header-menu" class="navbar-collapse">
                        <?php
                          wp_nav_menu(['theme_location' => 'primary_navigation', 'menu_class' => 'nav nav-stack', 'depth' => 1]);
                          $facebook_link = get_theme_mod('facebook_link');
                          $twitter_link = get_theme_mod('twitter_link');
                          $linkedin_link = get_theme_mod('linkedin_link');
                          $pinterest_link = get_theme_mod('pinterest_link');
                        ?>
                        <ul class="social-icons">
                            <?php if($twitter_link) { ?>
                                <li><a href="<?php echo esc_url($twitter_link); ?>" class="bt-icon socicon-twitter"><?php echo __('Twitter', 'konkurrent'); ?></a></li>
                            <?php } ?>
                            <?php if($linkedin_link) { ?>
                                <li><a href="<?php echo esc_url($linkedin_link); ?>" class="bt-icon socicon-linkedin"><?php echo __('Linkedin', 'konkurrent'); ?></a></li>
                            <?php } ?>
                            <?php if($facebook_link) { ?>
                                <li><a href="<?php echo esc_url($facebook_link); ?>" class="bt-icon socicon-facebook"><?php echo __('Facebook', 'konkurrent'); ?></a></li>
                            <?php } ?>
                            <?php if($pinterest_link) { ?>
                                <li><a href="<?php echo esc_url($pinterest_link); ?>" class="bt-icon socicon-pinterest"><?php echo __('Pinterest', 'konkurrent'); ?></a></li>
                            <?php } ?>
                        </ul>
                    </div>
                </nav>
            </div>
    <?php } ?>
</header>