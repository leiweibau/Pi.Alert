(function (window, document) {
  'use strict';
  var allowed = ['DIV','ARTICLE','H3','DL','DT','DD','SPAN','STRONG','B','EM','I','BR','TABLE','THEAD','TBODY','TR','TH','TD','P','SMALL','A'];
  function render(markup, output, expectedTarget, refresh) {
    var source = new DOMParser().parseFromString(String(markup == null ? '' : markup), 'text/html');
    var fragment = document.createDocumentFragment();
    function copy(node, parent) {
      if (node.nodeType === Node.TEXT_NODE) { parent.appendChild(document.createTextNode(node.nodeValue || '')); return; }
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      if (['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','FORM','META','LINK','IMG'].indexOf(node.tagName) >= 0) return;
      if (allowed.indexOf(node.tagName) < 0) { Array.from(node.childNodes).forEach(function (child) { copy(child,parent); }); return; }
      var element;
      if (node.tagName === 'A') {
        var target = node.getAttribute('data-target') || '';
        var link = node.getAttribute('href') || '';
        if (node.classList.contains('nmap-reload') && target === expectedTarget) {
          element = document.createElement('button'); element.type = 'button';
          element.addEventListener('click', refresh);
        } else if (link.indexOf('./download/hostnmapresultscvs.php?') === 0) {
          var url = new URL(link, window.location.href);
          if (url.origin !== window.location.origin || !url.pathname.endsWith('/download/hostnmapresultscvs.php') || url.searchParams.get('host') !== expectedTarget) return;
          element = document.createElement('a'); element.href = url.pathname + url.search;
        } else return;
      } else element = document.createElement(node.tagName.toLowerCase());
      if (typeof node.className === 'string') {
        var classes = node.className.split(/\s+/).filter(function (part) { return /^[A-Za-z0-9_-]+$/.test(part); });
        element.className = classes.join(' ');
      }
      ['scope','role','aria-label','title'].forEach(function (name) { if (node.hasAttribute(name)) element.setAttribute(name,node.getAttribute(name)); });
      Array.from(node.childNodes).forEach(function (child) { copy(child,element); });
      parent.appendChild(element);
    }
    Array.from(source.body.childNodes).forEach(function (node) { copy(node,fragment); });
    output.replaceChildren(fragment);
  }
  window.pialertNmapResults = {render:render};
})(window,document);
