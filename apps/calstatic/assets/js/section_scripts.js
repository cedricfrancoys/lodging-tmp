'use strict';
jQuery(document).ready(function($) {
    var doc = $(document)
      , win = $(window)
      , body = $('body')
      , header = $('.site-header')
      , headerHeight = header.outerHeight(true)
      , menuState = true
      , isNavAnimating = false
      , fakeScrollDiv = $('<div class="scrollbar-measure"></div>').appendTo(body)[0]      
      , scrollBarWidth = fakeScrollDiv.offsetWidth - fakeScrollDiv.clientWidth
      , menuBtn = $('.menu-toggle')
      , openMenuClass = ('is-nav-open')
      , mainNav = $('.main-navigation')
      , navMenu = mainNav.find('.menu')
      , hero = $('.hero');
   
    function openMenu() {

        if (!isNavAnimating) {
            isNavAnimating = true;
            body.addClass(openMenuClass);
            menuBtn.attr('aria-expanded', 'true');
            navMenu.attr('aria-expanded', 'true');
            if (win.height() < doc.height()) {
                body.css('margin-right', scrollBarWidth + 'px');
                header.css('right', scrollBarWidth + 'px');
                mainNav.css('padding-right', scrollBarWidth + 'px');
            }
            mainNav.css('overflow', 'hidden');
            menuState = false;
            mainNav.one('webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend', function() {
                isNavAnimating = false;
                mainNav.css('overflow', '');
            });
        }
    }
    
    function closeMenu() {
        if (!isNavAnimating) {
            isNavAnimating = true;
            body.removeClass(openMenuClass);
            menuBtn.attr('aria-expanded', 'false');
            navMenu.attr('aria-expanded', 'false');
            body.css('margin-right', '');
            header.css('right', '');
            mainNav.css('padding-right', '');
            mainNav.css('overflow', 'hidden');
            menuState = true;
            mainNav.one('webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend', function() {
                isNavAnimating = false;
                mainNav.css('overflow', '');
            });
        }
    }
    
    function onResizeMenu() {
        if (1020 > win.width()) {
            menuBtn.attr('aria-expanded', 'false');
            navMenu.attr('aria-expanded', 'false');
            menuBtn.attr('aria-controls', 'primary-menu');
        } else {
            menuBtn.removeAttr('aria-expanded');
            navMenu.removeAttr('aria-expanded');
            menuBtn.removeAttr('aria-controls');
            body.removeClass(openMenuClass);
            menuState = true;
        }
    }
    
    onResizeMenu();
    
    win.on('resize', onResizeMenu);
    
    menuBtn.on('click', function(e) {
        e.stopPropagation();
        if (menuState) {
            openMenu();
        } else {
            closeMenu();
        }
    });
    
    body.on('click', function(event) {
        if (!$(event.target).closest(navMenu.find('li')).length) {
            if (!menuState) {
                closeMenu();
            }
        }
    });
    
    // hidingHeader
    (function () {
        var heroHeight = hero.outerHeight(true)
          , headerHeight = header.outerHeight(true)
          , fixedClass = ('is-header-fixed')
          , hiddenClass = ('is-header-hidden')
          , visibleClass = ('is-header-visible')
          , isHeaderStatic = ('is-header-static')
          , isHero = ('is-hero')
          , heroOnClass = ('is-hero-on')
          , transitioningClass = ('is-header-transitioning');
        if (body.hasClass(isHero) && !body.hasClass(isHeaderStatic)) {
            var headerOffset = heroHeight;
            header.addClass(heroOnClass)
        } else {
            var headerOffset = headerHeight;
        }
    })();
    
    $('.scroll-icon').on('click', function() {
        var scrollOffset = $('.hero').height() + (($('.hero').outerHeight(true) - $('.hero').height()) / 2);
        $('html, body').animate({
            scrollTop: scrollOffset
        }, 300);
    });
});


