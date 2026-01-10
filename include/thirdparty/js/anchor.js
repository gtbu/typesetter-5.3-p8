
// https://github.com/bryanbraun/anchorjs MIT License
// modified by github.com/gtbu 1/2026

// https://github.com/umdjs/umd/blob/master/templates/returnExports.js

(function (root, factory) {
  'use strict';
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.AnchorJS = factory();
    root.anchors = new root.AnchorJS();
  }
}(globalThis, function () {
  'use strict';

  function decodeHtmlEntities(str) {
    if (str == null) return '';
    str = String(str);
    var entities = {
      '&amp;': '&',
      '&lt;': '<',
      '&gt;': '>',
      '&quot;': '"',
      '&#39;': '\'',
      '&nbsp;': ' '
    };
    return str.replace(/&amp;|&lt;|&gt;|&quot;|&#39;|&apos;|&#x27;|&nbsp;/g, function (m) {
      return entities[m];
    });
  }

  function applyDefaults(opts) {
    opts.icon = Object.prototype.hasOwnProperty.call(opts, 'icon') ? opts.icon : '\uE9CB';
    opts.visible = Object.prototype.hasOwnProperty.call(opts, 'visible') ? opts.visible : 'hover';
    opts.placement = Object.prototype.hasOwnProperty.call(opts, 'placement') ? opts.placement : 'right';
    opts.ariaLabel = Object.prototype.hasOwnProperty.call(opts, 'ariaLabel') ? opts.ariaLabel : 'Anchor';
    opts.class = Object.prototype.hasOwnProperty.call(opts, 'class') ? opts.class : '';
    opts.base = Object.prototype.hasOwnProperty.call(opts, 'base') ? opts.base : '';
    opts.truncate = Math.max(0, Math.floor(
      Object.prototype.hasOwnProperty.call(opts, 'truncate') ? opts.truncate : 64
    ));
    opts.titleText = Object.prototype.hasOwnProperty.call(opts, 'titleText') ? opts.titleText : '';
  }

  function AnchorJS(options) {
    this.options = options || {};
    applyDefaults(this.options);
    this.elements = [];

    var NONSAFE_CHARS = /[& +$,:;=?@"#{}|^~[`%!'<>\]./()*\\\n\t\b\v\u00A0]/g;

  this.add = function (selector) {
      var elements,
          elsWithIds,
          idSet,
          elementID,
          i,
          count,
          tidyText,
          newTidyText,
          anchor,
          indexesToDrop = [];

      applyDefaults(this.options);

      var baseEl = document.head.querySelector('base');
      var currentPath = window.location.pathname + window.location.search;
      var effectiveBase = this.options.base || (baseEl ? currentPath : '');

      if (!selector) {
        selector = 'h2, h3, h4, h5, h6';
      }

      elements = _getElements(selector);
      if (!elements.length) {
        return this;
      }

      _addBaselineStyles();

      elsWithIds = document.querySelectorAll('[id]');
      idSet = new Set();
      for (var j = 0; j < elsWithIds.length; j++) {
        if (elsWithIds[j].id) {
          idSet.add(elsWithIds[j].id);
        }
      }

      for (i = 0; i < elements.length; i++) {
        // Falls das Element schon einen AnchorJS-Link hat, überspringen
        if (this.hasAnchorJSLink(elements[i])) {
          indexesToDrop.push(i);
          continue;
        }

        // ID ermitteln oder generieren
        if (elements[i].hasAttribute('id')) {
          elementID = elements[i].getAttribute('id');
        } else if (elements[i].hasAttribute('data-anchor-id')) {
          elementID = elements[i].getAttribute('data-anchor-id');
        } else {
          
          tidyText = this.urlify(elements[i].textContent || '');
          var baseId = tidyText || 'section'; // Basis-ID fixieren
          newTidyText = baseId;
          count = 0;

          while (idSet.has(newTidyText)) {
            count++;
            newTidyText = baseId + '-' + count; // Nutze baseId statt tidyText
          }

          idSet.add(newTidyText);
          elements[i].setAttribute('id', newTidyText);
          elementID = newTidyText;
        }

        anchor = document.createElement('a');
        anchor.className = ('anchorjs-link ' + (this.options.class || '')).trim();
        anchor.setAttribute('aria-label', this.options.ariaLabel);
        anchor.setAttribute('data-anchorjs-icon', this.options.icon);
        if (this.options.titleText) {
          anchor.title = this.options.titleText;
        }

        anchor.href = effectiveBase + '#' + elementID;

        if (this.options.visible === 'always') {
          anchor.style.opacity = '1';
        }

        if (this.options.icon === '\uE9CB') {
          anchor.style.font = '1em/1 anchorjs-icons';
          if (this.options.placement === 'left') {
            anchor.style.lineHeight = 'inherit';
          }
        }

        if (this.options.placement === 'left') {
          anchor.style.position = 'absolute';
          anchor.style.marginLeft = '-1.25em';
          anchor.style.paddingRight = '.25em';
          anchor.style.paddingLeft = '.25em';
          elements[i].insertBefore(anchor, elements[i].firstChild);
        } else {
          anchor.style.marginLeft = '.1875em';
          anchor.style.paddingRight = '.1875em';
          anchor.style.paddingLeft = '.1875em';
          elements[i].appendChild(anchor);
        }
      }

      for (i = 0; i < indexesToDrop.length; i++) {
        elements.splice(indexesToDrop[i] - i, 1);
      }

      this.elements = this.elements.concat(elements);
      return this;
    };

    this.remove = function (selector) {
      var index, domAnchor, elements = _getElements(selector);
      for (var i = 0; i < elements.length; i++) {
        domAnchor = elements[i].querySelector('.anchorjs-link');
        if (domAnchor) {
          index = this.elements.indexOf(elements[i]);
          if (index !== -1) {
            this.elements.splice(index, 1);
          }
          elements[i].removeChild(domAnchor);
        }
      }
      return this;
    };

    this.removeAll = function () {
      this.remove(this.elements);
    };

    this.urlify = function (text) {
      text = decodeHtmlEntities(text);
      applyDefaults(this.options);

      return text.trim()
        .replace(/'/gi, '')
        .replace(NONSAFE_CHARS, '-')
        .replace(/-{2,}/g, '-')
        .substring(0, this.options.truncate)
        .replace(/^-+|-+$/gm, '')
        .toLowerCase();
    };

    this.hasAnchorJSLink = function (el) {
      var first = el.firstChild;
      var last = el.lastChild;
      var hasLeftAnchor = first && typeof first.className === 'string' &&
        (' ' + first.className + ' ').indexOf(' anchorjs-link ') > -1;
      var hasRightAnchor = last && typeof last.className === 'string' &&
        (' ' + last.className + ' ').indexOf(' anchorjs-link ') > -1;
      return hasLeftAnchor || hasRightAnchor || false;
    };

    function _getElements(input) {
      var elements;
      if (typeof input === 'string' || input instanceof String) {
        elements = [].slice.call(document.querySelectorAll(input));
      } else if (Array.isArray(input) || input instanceof NodeList) {
        elements = [].slice.call(input);
      } else {
        throw new TypeError('The selector provided to AnchorJS was invalid.');
      }
      return elements;
    }

    function _addBaselineStyles() {
      if (document.head.querySelector('style.anchorjs') !== null) {
        return;
      }

      var style = document.createElement('style'),
          linkRule = '.anchorjs-link{opacity:0;text-decoration:none;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}',
          hoverRule = ':hover>.anchorjs-link,.anchorjs-link:focus{opacity:1}',
          anchorjsLinkFontFace =
            '@font-face{font-family:anchorjs-icons;src:url(data:n/a;base64,AAEAAAALAIAAAwAwT1MvMg8yG2cAAAE4AAAAYGNtYXDp3gC3AAABpAAAAExnYXNwAAAAEAAAA9wAAAAIZ2x5ZlQCcfwAAAH4AAABCGhlYWQHFvHyAAAAvAAAADZoaGVhBnACFwAAAPQAAAAkaG10eASAADEAAAGYAAAADGxvY2EACACEAAAB8AAAAAhtYXhwAAYAVwAAARgAAAAgbmFtZQGOH9cAAAMAAAAAunBvc3QAAwAAAAADvAAAACAAAQAAAAEAAHzE2p9fDzz1AAkEAAAAAADRecUWAAAAANQA6R8AAAAAAoACwAAAAAgAAgAAAAAAAAABAAADwP/AAAACgAAA/9MCrQABAAAAAAAAAAAAAAAAAAAAAwABAAAAAwBVAAIAAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAMCQAGQAAUAAAKZAswAAACPApkCzAAAAesAMwEJAAAAAAAAAAAAAAAAAAAAARAAAAAAAAAAAAAAAAAAAAAAQAAg//0DwP/AAEADwABAAAAAAQAAAAAAAAAAAAAAIAAAAAAAAAIAAAACgAAxAAAAAwAAAAMAAAAcAAEAAwAAABwAAwABAAAAHAAEADAAAAAIAAgAAgAAACDpy//9//8AAAAg6cv//f///+EWNwADAAEAAAAAAAAAAAAAAAAACACEAAEAAAAAAAAAAAAAAAAxAAACAAQARAKAAsAAKwBUAAABIiYnJjQ3NzY2MzIWFxYUBwcGIicmNDc3NjQnJiYjIgYHBwYUFxYUBwYGIwciJicmNDc3NjIXFhQHBwYUFxYWMzI2Nzc2NCcmNDc2MhcWFAcHBgYjARQGDAUtLXoWOR8fORYtLTgKGwoKCjgaGg0gEhIgDXoaGgkJBQwHdR85Fi0tOAobCgoKOBoaDSASEiANehoaCQkKGwotLXoWOR8BMwUFLYEuehYXFxYugC44CQkKGwo4GkoaDQ0NDXoaShoKGwoFBe8XFi6ALjgJCQobCjgaShoNDQ0NehpKGgobCgoKLYEuehYXAAAADACWAAEAAAAAAAEACAAAAAEAAAAAAAIAAwAIAAEAAAAAAAMACAAAAAEAAAAAAAQACAAAAAEAAAAAAAUAAQALAAEAAAAAAAYACAAAAAMAAQQJAAEAEAAMAAMAAQQJAAIABgAcAAMAAQQJAAMAEAAMAAMAAQQJAAQAEAAMAAMAAQQJAAUAAgAiAAMAAQQJAAYAEAAMYW5jaG9yanM0MDBAAGEAbgBjAGgAbwByAGoAcwA0ADAAMABAAAAAAwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAAH//wAP) format("truetype")}',
          pseudoElContent = '[data-anchorjs-icon]::after{content:attr(data-anchorjs-icon)}',
          firstStyleEl;

      style.className = 'anchorjs';
      style.appendChild(document.createTextNode(''));
      firstStyleEl = document.head.querySelector('[rel="stylesheet"],style');
      if (!firstStyleEl) {
        document.head.appendChild(style);
      } else {
        document.head.insertBefore(style, firstStyleEl);
      }

      style.sheet.insertRule(linkRule, style.sheet.cssRules.length);
      style.sheet.insertRule(hoverRule, style.sheet.cssRules.length);
      style.sheet.insertRule(pseudoElContent, style.sheet.cssRules.length);
      style.sheet.insertRule(anchorjsLinkFontFace, style.sheet.cssRules.length);
    }
  }

  return AnchorJS;
}));