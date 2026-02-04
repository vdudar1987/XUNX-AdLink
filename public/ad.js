(function () {
  var script = document.currentScript;
  if (!script) {
    return;
  }

  var endpoint = script.getAttribute('data-endpoint');
  var clickEndpoint = script.getAttribute('data-click-endpoint');
  var impressionEndpoint = script.getAttribute('data-impression-endpoint');
  var demoAdsRaw = script.getAttribute('data-demo-ads');
  var publisherId = parseInt(script.getAttribute('data-publisher-id') || '0', 10);

  if ((!endpoint && !demoAdsRaw) || !clickEndpoint || !publisherId) {
    return;
  }

  function getFingerprint() {
    var key = 'adlink_fp';
    try {
      var stored = localStorage.getItem(key);
      if (stored) {
        return stored;
      }
      var value = Math.random().toString(36).slice(2) + Date.now().toString(36);
      localStorage.setItem(key, value);
      return value;
    } catch (err) {
      return '';
    }
  }

  var pageLoadTime = Date.now();

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

  function loadDemoAds() {
    if (!demoAdsRaw) {
      return null;
    }
    try {
      var parsed = JSON.parse(demoAdsRaw);
      return Array.isArray(parsed) ? parsed : null;
    } catch (err) {
      return null;
    }
  }

  function requestAds(keywords) {
    var demoAds = loadDemoAds();
    if (demoAds) {
      return Promise.resolve(demoAds);
    }

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
    toolbar.style.maxWidth = '260px';
    toolbar.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
    toolbar.style.opacity = '0';
    toolbar.style.transform = 'translateY(6px)';

    var header = document.createElement('div');
    header.style.display = 'flex';
    header.style.alignItems = 'center';
    header.style.justifyContent = 'space-between';
    header.style.marginBottom = '6px';

    var title = document.createElement('div');
    title.className = 'adlink-title';
    title.style.fontWeight = '600';
    title.style.marginRight = '8px';
    title.style.flex = '1';

    var close = document.createElement('button');
    close.type = 'button';
    close.textContent = '×';
    close.style.background = 'transparent';
    close.style.border = 'none';
    close.style.color = '#fff';
    close.style.cursor = 'pointer';
    close.style.fontSize = '16px';
    close.style.lineHeight = '1';

    header.appendChild(title);
    header.appendChild(close);

    var body = document.createElement('div');
    body.className = 'adlink-body';
    body.style.display = 'flex';
    body.style.gap = '8px';
    body.style.marginBottom = '8px';

    var image = document.createElement('img');
    image.style.width = '64px';
    image.style.height = '64px';
    image.style.objectFit = 'cover';
    image.style.borderRadius = '4px';
    image.style.display = 'none';

    var teaser = document.createElement('div');
    teaser.style.fontSize = '11px';
    teaser.style.lineHeight = '1.4';
    teaser.style.color = '#e5e7eb';

    body.appendChild(image);
    body.appendChild(teaser);

    var button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Перейти';
    button.style.background = '#22c55e';
    button.style.border = 'none';
    button.style.color = '#fff';
    button.style.padding = '6px 10px';
    button.style.borderRadius = '4px';
    button.style.cursor = 'pointer';

    toolbar.appendChild(header);
    toolbar.appendChild(body);
    toolbar.appendChild(button);
    document.body.appendChild(toolbar);

    return {
      toolbar: toolbar,
      title: title,
      button: button,
      close: close,
      image: image,
      teaser: teaser
    };
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
      toolbarData.toolbar.style.opacity = '0';
      toolbarData.toolbar.style.transform = 'translateY(6px)';
      activeAd = null;
    }

    toolbarData.toolbar.addEventListener('mouseleave', hideToolbar);
    toolbarData.close.addEventListener('click', hideToolbar);

    toolbarData.button.addEventListener('click', function () {
      if (!activeAd) {
        return;
      }
      var timeOnPageMs = Date.now() - pageLoadTime;
      fetch(clickEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          publisher_id: publisherId,
          campaign_id: activeAd.campaign_id,
          keyword_id: activeAd.keyword_id,
          page_url: location.href,
          fingerprint: getFingerprint(),
          time_on_page_ms: timeOnPageMs
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
    var usedAdvertisers = {};
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        if (!node.parentNode) {
          return NodeFilter.FILTER_REJECT;
        }
        var tag = node.parentNode.nodeName.toLowerCase();
        var blockedTags = [
          'script',
          'style',
          'noscript',
          'iframe',
          'a',
          'h1',
          'h2',
          'h3',
          'h4',
          'h5',
          'h6',
          'code',
          'pre',
          'blockquote',
          'q',
          'cite'
        ];
        if (blockedTags.indexOf(tag) !== -1) {
          return NodeFilter.FILTER_REJECT;
        }
        var parent = node.parentNode;
        while (parent && parent !== document.body) {
          if (blockedTags.indexOf(parent.nodeName.toLowerCase()) !== -1) {
            return NodeFilter.FILTER_REJECT;
          }
          parent = parent.parentNode;
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
      if (usedAdvertisers[ad.advertiser_id]) {
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
            toolbarData.teaser.textContent = ad.teaser_text || 'Реклама от проверенного рекламодателя.';
            if (ad.image_url) {
              toolbarData.image.src = ad.image_url;
              toolbarData.image.style.display = 'block';
            } else {
              toolbarData.image.style.display = 'none';
            }
            positionToolbar(toolbarData.toolbar, span);
            toolbarData.toolbar.style.display = 'block';
            requestAnimationFrame(function () {
              toolbarData.toolbar.style.opacity = '1';
              toolbarData.toolbar.style.transform = 'translateY(0)';
            });
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
            toolbarData.teaser.textContent = ad.teaser_text || 'Реклама от проверенного рекламодателя.';
            if (ad.image_url) {
              toolbarData.image.src = ad.image_url;
              toolbarData.image.style.display = 'block';
            } else {
              toolbarData.image.style.display = 'none';
            }
            positionToolbar(toolbarData.toolbar, span);
            toolbarData.toolbar.style.display = 'block';
            requestAnimationFrame(function () {
              toolbarData.toolbar.style.opacity = '1';
              toolbarData.toolbar.style.transform = 'translateY(0)';
            });
          });
        });
        if (found) {
          placed += 1;
          usedAdvertisers[ad.advertiser_id] = true;
          nodes.splice(i, 1);
          if (impressionEndpoint) {
            fetch(impressionEndpoint, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                publisher_id: publisherId,
                campaign_id: ad.campaign_id,
                keyword_id: ad.keyword_id,
                page_url: location.href
              }),
              keepalive: true
            });
          }
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
