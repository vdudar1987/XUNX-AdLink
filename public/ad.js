(function () {
  var script = document.currentScript;
  if (!script) {
    return;
  }

  var endpoint = script.getAttribute('data-endpoint');
  var clickEndpoint = script.getAttribute('data-click-endpoint');
  var publisherId = parseInt(script.getAttribute('data-publisher-id') || '0', 10);

  if (!endpoint || !clickEndpoint || !publisherId) {
    return;
  }

  function collectKeywords() {
    var text = document.body ? document.body.innerText || '' : '';
    text = text.replace(/[\n\r]+/g, ' ');
    var words = text
      .toLowerCase()
      .replace(/[^a-zа-я0-9ё\s]+/gi, ' ')
      .split(/\s+/)
      .filter(function (word) {
        return word.length >= 3 && word.length <= 32;
      });

    var unique = {};
    var result = [];
    for (var i = 0; i < words.length && result.length < 50; i += 1) {
      var word = words[i];
      if (!unique[word]) {
        unique[word] = true;
        result.push(word);
      }
    }
    return result;
  }

  function requestAds(keywords) {
    return fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ publisher_id: publisherId, keywords: keywords })
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        return payload.ads || [];
      })
      .catch(function () {
        return [];
      });
  }

  function createToolbar() {
    var toolbar = document.createElement('div');
    toolbar.className = 'adlink-toolbar';
    toolbar.style.position = 'absolute';
    toolbar.style.background = '#1f2937';
    toolbar.style.color = '#fff';
    toolbar.style.padding = '8px 10px';
    toolbar.style.borderRadius = '6px';
    toolbar.style.fontSize = '12px';
    toolbar.style.zIndex = '99999';
    toolbar.style.boxShadow = '0 6px 16px rgba(0,0,0,0.2)';
    toolbar.style.display = 'none';

    var title = document.createElement('div');
    title.className = 'adlink-title';
    title.style.fontWeight = '600';
    title.style.marginBottom = '6px';

    var button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Перейти';
    button.style.background = '#22c55e';
    button.style.border = 'none';
    button.style.color = '#fff';
    button.style.padding = '6px 10px';
    button.style.borderRadius = '4px';
    button.style.cursor = 'pointer';

    toolbar.appendChild(title);
    toolbar.appendChild(button);
    document.body.appendChild(toolbar);

    return { toolbar: toolbar, title: title, button: button };
  }

  function positionToolbar(toolbar, target) {
    var rect = target.getBoundingClientRect();
    toolbar.style.left = rect.left + window.scrollX + 'px';
    toolbar.style.top = rect.bottom + window.scrollY + 6 + 'px';
  }

  function replaceWord(node, word, replacement) {
    var index = node.nodeValue.toLowerCase().indexOf(word.toLowerCase());
    if (index === -1) {
      return false;
    }

    var original = node.nodeValue;
    var before = document.createTextNode(original.slice(0, index));
    var after = document.createTextNode(original.slice(index + word.length));
    var span = document.createElement('span');
    span.textContent = original.substr(index, word.length);
    span.className = 'adlink-word';
    span.style.fontWeight = '700';
    span.style.textDecoration = 'underline';
    span.style.cursor = 'pointer';

    var fragment = document.createDocumentFragment();
    fragment.appendChild(before);
    fragment.appendChild(span);
    fragment.appendChild(after);
    node.parentNode.replaceChild(fragment, node);

    replacement(span);

    return true;
  }

  function injectAds(ads) {
    if (!ads.length) {
      return;
    }

    var toolbarData = createToolbar();
    var activeAd = null;

    function hideToolbar() {
      toolbarData.toolbar.style.display = 'none';
      activeAd = null;
    }

    toolbarData.toolbar.addEventListener('mouseleave', hideToolbar);

    toolbarData.button.addEventListener('click', function () {
      if (!activeAd) {
        return;
      }
      fetch(clickEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          publisher_id: publisherId,
          campaign_id: activeAd.campaign_id,
          keyword_id: activeAd.keyword_id,
          page_url: location.href
        }),
        keepalive: true
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (payload.allowed) {
            window.location.href = activeAd.landing_url;
          }
        });
    });

    var placed = 0;
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        if (!node.parentNode) {
          return NodeFilter.FILTER_REJECT;
        }
        var tag = node.parentNode.nodeName.toLowerCase();
        if (['script', 'style', 'noscript', 'iframe'].indexOf(tag) !== -1) {
          return NodeFilter.FILTER_REJECT;
        }
        if (!node.nodeValue || node.nodeValue.trim() === '') {
          return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
      }
    });

    var nodes = [];
    while (walker.nextNode()) {
      nodes.push(walker.currentNode);
    }

    ads.forEach(function (ad) {
      if (placed >= 3) {
        return;
      }
      var word = ad.keyword;
      for (var i = 0; i < nodes.length; i += 1) {
        if (placed >= 3) {
          break;
        }
        var node = nodes[i];
        var found = replaceWord(node, word, function (span) {
          span.addEventListener('mouseenter', function () {
            activeAd = ad;
            toolbarData.title.textContent = ad.title || ad.keyword;
            positionToolbar(toolbarData.toolbar, span);
            toolbarData.toolbar.style.display = 'block';
          });
          span.addEventListener('mouseleave', function () {
            setTimeout(function () {
              if (!toolbarData.toolbar.matches(':hover')) {
                hideToolbar();
              }
            }, 150);
          });
          span.addEventListener('click', function () {
            activeAd = ad;
            toolbarData.title.textContent = ad.title || ad.keyword;
            positionToolbar(toolbarData.toolbar, span);
            toolbarData.toolbar.style.display = 'block';
          });
        });
        if (found) {
          placed += 1;
          nodes.splice(i, 1);
          break;
        }
      }
    });
  }

  var keywords = collectKeywords();
  if (!keywords.length) {
    return;
  }

  requestAds(keywords).then(injectAds);
})();
