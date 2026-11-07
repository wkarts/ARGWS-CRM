(function () {
    'use strict';

  window.init_btn_with_tooltips = function () {
    var $ = window.jQuery;
    if (!is_mobile() || navigator.userAgent.match(/iPad/i)) return false;
    $('._buttons').find('.btn-with-tooltip, .btn-with-tooltip-group').each(function () {
      var node = $(this), title = node.attr('title') || node.attr('data-title');
      if (!title) return;
      if (node.hasClass('btn-with-tooltip-group')) node.find('.btn').eq(0).append(' ' + title);
      else node.append(' ' + title);
      node.removeClass('btn-with-tooltip btn-with-tooltip-group');
    });
  };

  window.init_tabs_scrollable = function () {
    var $ = window.jQuery;
    if (isRTL !== 'true') {
      if ($(window).width() <= 768) $('body').find('.toggle_view').remove();
      $('.horizontal-scrollable-tabs').horizontalTabs();
      return;
    }
    $('.arrow-left, .arrow-right').hide();
    $('.horizontal-scrollable-tabs').removeClass('horizontal-scrollable-tabs');
    $('.nav-tabs-horizontal').removeClass('nav-tabs-horizontal');
  };


    function tabParts(tab) {
        var tablist = tab.closest('[role="tablist"]');
        var panelBody = tablist && tablist.closest('.panel-body');
        var content = panelBody && panelBody.querySelector('.tab-content');
        var href = tab.getAttribute('href') || '';
        var panel = href.charAt(0) === '#' ? document.getElementById(href.slice(1)) : null;

        if (!tablist || !panelBody || !content || !panel || !content.contains(panel)) {
            return null;
        }

        var tabs = Array.prototype.filter.call(
            tablist.querySelectorAll('[data-toggle="tab"][role="tab"]'),
            function (candidate) {
                return candidate.closest('[role="tablist"]') === tablist;
            }
        );

        return tabs.indexOf(tab) === -1 ? null : { content: content, panel: panel, tabs: tabs };
    }

    function activate(tab, moveFocus) {
        var parts = tabParts(tab);
        if (!parts) {
            return false;
        }

        parts.tabs.forEach(function (item) {
            var itemParts = tabParts(item);
            if (!itemParts) {
                return;
            }

            var active = item === tab;
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.setAttribute('tabindex', active ? '0' : '-1');
            var listItem = item.closest('li');
            if (listItem) {
                listItem.classList.toggle('active', active);
            }
            itemParts.panel.classList.toggle('active', active);
            itemParts.panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });

        if (moveFocus) {
            tab.focus();
        }
        return true;
    }

    function activateHash() {
        document.querySelectorAll('.company-context-shell [role="tablist"] [data-toggle="tab"][role="tab"], .native-company-admin [role="tablist"] [data-toggle="tab"][role="tab"]').forEach(function (tab) {
            if (tab.getAttribute('href') === window.location.hash) { activate(tab, false); }
        });
    }
    window.addEventListener('hashchange', activateHash);
    activateHash();

    document.querySelectorAll('.company-context-shell [role="tablist"] [data-toggle="tab"][role="tab"], .native-company-admin [role="tablist"] [data-toggle="tab"][role="tab"]').forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            if (activate(tab, false)) {
                event.preventDefault();
            }
        });

        tab.addEventListener('keydown', function (event) {
            var parts = tabParts(tab);
            if (!parts) {
                return;
            }

            var index = parts.tabs.indexOf(tab);
            var nextIndex;
            if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % parts.tabs.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (index + parts.tabs.length - 1) % parts.tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = parts.tabs.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            activate(parts.tabs[nextIndex], true);
        });
    });
}());
