/* Gallery asset lifecycle shared by PDPs and Quickviews. Included in core.js by the build. */
O46Core.magicZoomUrl = O46Core.magicZoomUrl || (document.currentScript && document.currentScript.getAttribute('data-eg-magiczoom-src'));
O46Core.ensureMagicZoom = function () {
    if (O46Core.zoomPromise) return O46Core.zoomPromise;
    if (window.MagicZoom) return new Promise(function (resolve) {
        if (document.jAddEvent) document.jAddEvent('domready', function () { setTimeout(function () { resolve(window.MagicZoom); }, 0); }, 100);
        else resolve(window.MagicZoom);
    });
    O46Core.zoomPromise = new Promise(function (resolve, reject) {
        var script = document.querySelector('script[data-eg-magiczoom]');
        var created = !script;
        if (!script) {
            script = document.createElement('script');
            script.setAttribute('data-eg-magiczoom', '');
            script.src = O46Core.magicZoomUrl;
        }
        var timer;
        function finish(error) {
            clearTimeout(timer);
            script.removeEventListener('load', loaded);
            script.removeEventListener('error', failed);
            if (error) {
                script.remove();
                reject(error);
            } else resolve(window.MagicZoom);
        }
        function loaded() {
            if (!window.MagicZoom || !document.jAddEvent) return finish(new Error('MagicZoom did not initialize'));
            // The shipped vendor schedules its own domready initialization even
            // when loaded after window.load. Its script load event is too early.
            document.jAddEvent('domready', function () {
                setTimeout(function () { finish(null); }, 0);
            }, 100);
        }
        function failed() { finish(new Error('MagicZoom could not be loaded')); }
        script.addEventListener('load', loaded);
        script.addEventListener('error', failed);
        timer = setTimeout(failed, 15000);
        if (created) {
            if (!O46Core.magicZoomUrl) failed();
            else document.head.appendChild(script);
        }
    }).catch(function (error) {
        O46Core.zoomPromise = null; // A later interaction may retry a failed download.
        throw error;
    });
    return O46Core.zoomPromise;
};

O46Core.startInitialGallery = function () {
    // Stored content can contain a gallery outside a PDP. Keep that extension
    // boundary working without downloading zoom on pages that have no gallery.
    if (document.querySelector('script[data-eg-magiczoom]') || !document.querySelector('.MagicZoom, .MagicZoomPlus')) return;
    O46Core.ensureMagicZoom().catch(function (error) {
        // The original image link remains usable if the optional asset fails.
        console.warn('Gallery zoom unavailable; image links remain available.', error);
    });
};

O46Core.openQuickview = function (trigger, bindDelay) {
    var view = O46Core.quickview;
    if (!view) {
        var modal = $('.modal-cross');
        var opener;
        view = O46Core.quickview = { phase: 'hidden', current: null, reads: Object.create(null) };
        view.apply = function () {
            // Bootstrap ignores show/hide during transitions. Reconcile only
            // after its public completion events, including backdrop cleanup.
            if (view.phase === 'opening' || view.phase === 'closing') return;
            var request = view.current;
            if (!request) {
                if (view.phase === 'shown') modal.modal('hide');
                return;
            }
            if (!request.html || request.rendered) return;
            request.rendered = true;
            opener = request.trigger;
            $('.modal-cross .modal-body').html(request.html);
            MagicZoom.start();
            modal.modal('show', opener);
            setTimeout(function () {
                if (view.current === request) eGuardians.events.bindEvents('.modal-cross [data-eg-event]');
            }, request.bindDelay);
        };
        function cancel() { view.current = null; }
        modal.on('show.bs.modal.o46Quickview', function () { view.phase = 'opening'; })
            .on('shown.bs.modal.o46Quickview', function () { view.phase = 'shown'; view.apply(); })
            .on('hide.bs.modal.o46Quickview', function () { view.phase = 'closing'; cancel(); })
            .on('hidden.bs.modal.o46Quickview', function () {
                view.phase = 'hidden';
                if (!view.current && opener && opener.isConnected &&
                    (document.activeElement === document.body || this.contains(document.activeElement))) {
                    opener.focus({ preventScroll: true });
                }
                opener = null;
                view.apply();
            })
            // A dismiss click during opening is ignored by Bootstrap too.
            // Remember it even when no hide event is emitted; shown will hide.
            .on('click.o46Quickview', '[data-dismiss="modal"]', cancel)
            .on('click.o46Quickview', function (event) {
                if (event.target === this && view.phase === 'opening') cancel();
            });
        $(document).on('keydown.o46Quickview', function (event) {
            if (event.which === 27 && (view.phase === 'opening' || view.phase === 'hidden')) cancel();
        });
    }
    var url = new URL($(trigger).data('href'), window.location.href);
    url.searchParams.set('quickViewItem', 'true');
    if (view.current && view.current.url === url.href) return false;
    var request = view.current = { url: url.href, bindDelay: bindDelay, trigger: trigger };
    // Share only in-flight reads, including a close/reopen before the response.
    // Completed HTML is not cached across openings or language changes.
    var html = view.reads[url.href];
    if (!html) {
        html = view.reads[url.href] = new Promise(function (resolve, reject) {
            // Backend-rendered HTML gets the same budget as the variation batch.
            // Ready assets must not leave a stalled read waiting indefinitely.
            eGuardians.apiservice.get(url.href, '', function (response) {
                if (typeof response === 'string' && response.trim()) resolve(response);
                else reject(new Error('Quickview response is empty or invalid'));
            }, 'html', 30000);
        });
        var forget = function () { delete view.reads[url.href]; };
        html.then(forget, forget);
    }
    Promise.all([html, O46Core.ensureMagicZoom()]).then(function (results) {
        if (view.current !== request) return;
        request.html = results[0];
        view.apply();
    }).catch(function (error) {
        if (view.current !== request) return;
        view.current = null;
        console.warn('Quickview unavailable; opening the product page.', error);
        // Keep the product reachable after a failed asset or HTML request.
        url.searchParams.delete('quickViewItem');
        window.location.assign(url.href);
    });
    return false;
};

// Shared gallery update for the standard variation selector.
(function () {
  function pickImages(images) {
    if (!images) return [];

    var arr = (images.variation && images.variation.length)
      ? images.variation
      : (images.all && images.all.length ? images.all : []);

    if (!arr || !arr.length) return [];

    var out = [];
    var seen = new Set();

    for (var i = 0; i < arr.length; i++) {
      var im = arr[i];
      var full = im && (im.url || im.urlLarge || im.urlMiddle || im.urlPreview);
      if (!full || seen.has(full)) continue;
      seen.add(full);
      out.push({
        url: full,
        urlMiddle: im.urlMiddle || im.url || null,
        urlPreview: im.urlPreview || im.urlMiddle || im.url || null,
        position: (typeof im.position === "number") ? im.position : 9999
      });
    }

    out.sort(function (a, b) {
      return a.position - b.position;
    });

    return out;
  }
  function escapeHtml(s) {
    s = (s == null) ? "" : String(s);

    return s.replace(/[&<>"']/g, function (ch) {
      return ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      })[ch];
    });
  }

  function getProductTitleForGallery(article) {
    var scope = article && article.closest("article");
    var h1 = (scope || document).querySelector("h1");
    var t2 = (h1 && h1.textContent) ? h1.textContent : document.title;
    return String(t2 || "").trim();
  }

  function normalizeThumbUrl(u) {
    u = (u == null) ? "" : String(u);

    if (u.indexOf("/preview/") > -1) {
      u = u.replace("/preview/", "/secondPreview/");
    }

    return u;
  }

  function ensureZoomAnchor(zoomId) {
    var a = document.getElementById(zoomId);
    if (!a) return null;

    if (!a.id) a.id = zoomId;

    if (a.classList && !a.classList.contains("MagicZoom")) {
      a.classList.add("MagicZoom");
    }

    return a;
  }

  function setMainImageNow(zoomId, full, middle, title) {
    var a = ensureZoomAnchor(zoomId);
    if (!a) return;

    a.setAttribute("href", full);
    a.setAttribute("title", title || "");
    a.setAttribute("data-image", middle);
    a.setAttribute("data-zoom-image", full);

    var img = a.querySelector("img");

    if (img) {
      img.setAttribute("src", middle);
      img.setAttribute("alt", title || "");
      img.setAttribute("title", title || "");
    }
  }

  function updateMagicZoomWithGallery(article, zoomId, images) {
    if (!zoomId || !images || !images.length) return;
    if (!document.getElementById(zoomId)) return;
    if (!window.MagicZoom) return;

    var a = ensureZoomAnchor(zoomId);
    if (!a) return;

    var first = images[0] || null;
    if (!first) return;

    var firstFull = first.url || first.urlLarge || first.urlMiddle || first.urlPreview || null;
    var firstMiddle = first.urlMiddle || first.url || first.urlLarge || first.urlPreview || null;

    if (!firstFull || !firstMiddle) return;

    var scope = article.closest("article") || document;
    var prev = scope.querySelector(".single-prev-images");

    // Dispose selector handlers before replacing their elements.
    MagicZoom.stop(zoomId);

    var ttl = getProductTitleForGallery(article);
    var ttlEsc = escapeHtml(ttl);

    if (prev) {
      var html = "";

      for (var i = 0; i < images.length; i++) {
        var im = images[i];
        if (!im) continue;

        var full = im.url || im.urlLarge || im.urlMiddle || im.urlPreview || null;
        var mid = im.urlMiddle || im.url || im.urlLarge || im.urlPreview || null;
        var th = im.urlPreview || im.urlMiddle || im.url || im.urlLarge || null;

        if (!full || !mid || !th) continue;

        th = normalizeThumbUrl(th);

        var cls = "white-bg mz-thumb" + (i === 0 ? " mz-thumb-selected" : "");

        html += '<a href="' + escapeHtml(full) + '" rel="zoom-id:' + escapeHtml(zoomId) + '" data-image="' + escapeHtml(mid) + '" title="' + ttlEsc + '" data-stepindex="' + String(i) + '" class="' + cls + '">'
          + '<img src="' + escapeHtml(th) + '" data-src="' + escapeHtml(th) + '" alt="' + ttlEsc + '" title="' + ttlEsc + '" class="lozad" width="100%" height="100%" data-loaded="true" />'
          + "</a>";
      }

      if (html) prev.innerHTML = html;
      if (prev.closest('[aria-hidden="true"]')) {
        prev.querySelectorAll("a").forEach(function (selector) { selector.tabIndex = -1; });
      }
    }

    var frame = a.closest(".eg-gallery-frame");
    if (frame) frame.dataset.egGalleryCount = String(images.length);
    setMainImageNow(zoomId, firstFull, firstMiddle, ttl);

    MagicZoom.start(zoomId);
  }
  O46Core.setVariationGallery = function (article, images) {
    var anchor = article.querySelector(".eg-gallery-frame .MagicZoom, .eg-gallery-frame .MagicZoomPlus");
    if (anchor) updateMagicZoomWithGallery(article, anchor.id, pickImages(images));
  };
})();
