/* Perilaku antarmuka sekolah menggunakan pustaka lokal yang tersedia. */
(function () {
  'use strict';

  // Di development, muat ulang stylesheet agar perubahan langsung terlihat.
  const refreshDevelopmentAssets = function () {
    const siteBase = new URL(document.body.dataset.baseUrl || './', document.baseURI);
    const endpoint = new URL('cache-mode.php', siteBase);
    endpoint.searchParams.set('_', String(Date.now()));
    fetch(endpoint, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (environment) {
        if (!environment || environment.mode_environment !== 'DEVELOPMENT') return;
        const version = String(Date.now());
        document.querySelectorAll('link[rel="stylesheet"]').forEach(function (stylesheet) {
          const url = new URL(stylesheet.href, document.baseURI);
          url.searchParams.set('dev', version);
          stylesheet.href = url.href;
        });
      })
      .catch(function () {
        // Cache busting tidak boleh mengganggu fungsi utama halaman.
      });
  };
  // Halaman PHP sudah memberikan versi aset sebelum browser memuatnya.
  if (!document.body.dataset.environment) refreshDevelopmentAssets();

  // Pasang popup sebelum inisialisasi slider dan efek halaman.
  // Foto video memiliki aksi putar tersendiri, bukan pratinjau galeri foto.
  const modalElement = document.getElementById('galleryModal');
  if (modalElement && window.bootstrap?.Modal) {
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const preview = document.getElementById('galleryPreview');
    let trigger = null;
    let fullSizeImage = null;
    const cancelFullSize = function () {
      if (!fullSizeImage) return;
      fullSizeImage.onload = null;
      fullSizeImage.onerror = null;
      fullSizeImage = null;
    };
    document.querySelectorAll('main img').forEach(function (img) {
      if (img.closest('.video-card')) return;
      img.classList.add('image-preview');
      img.setAttribute('role', 'button');
      img.setAttribute('tabindex', '0');
      img.setAttribute('aria-label', 'Perbesar gambar: ' + img.alt);
      img.setAttribute('aria-haspopup', 'dialog');
      img.setAttribute('aria-controls', 'galleryModal');
      const open = function () {
        cancelFullSize();
        if (img.closest('.teacher-card')) img.classList.add('is-color');
        trigger = img;
        // Tampilkan foto yang sudah dimuat tanpa menunggu unduhan versi asli.
        const thumbnailSrc = img.currentSrc || img.src;
        preview.src = thumbnailSrc;
        preview.alt = img.alt;
        modal.show();
        const fullSrc = img.dataset.fullSrc;
        if (fullSrc && fullSrc !== thumbnailSrc) {
          const candidate = new Image();
          fullSizeImage = candidate;
          candidate.onload = function () {
            if (fullSizeImage !== candidate) return;
            preview.src = fullSrc;
            cancelFullSize();
          };
          // Pertahankan thumbnail jika gambar asli gagal dimuat.
          candidate.onerror = cancelFullSize;
          candidate.src = fullSrc;
        }
      };
      img.addEventListener('click', open);
      img.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          open();
        }
      });
    });
    // Bersihkan gambar dan kembalikan fokus ke foto yang sebelumnya dipilih.
    modalElement.addEventListener('hide.bs.modal', cancelFullSize);
    modalElement.addEventListener('hidden.bs.modal', function () {
      preview.removeAttribute('src');
      preview.alt = '';
      if (trigger) trigger.focus({ preventScroll: true });
    });
  }

  // Sinkronkan bayangan navbar tanpa mengubah tata letak saat menggulir.
  const navbar = document.querySelector('.custom-navbar');
  const updateNavbar = function () {
    if (navbar) navbar.classList.toggle('navbar-shrink', window.scrollY > 15);
  };
  window.addEventListener('scroll', updateNavbar, { passive: true });
  updateNavbar();

  // Tutup menu dahulu, lalu pindahkan fokus dan posisi ke bagian tujuan.
  const menu = document.getElementById('offcanvasMenu');
  if (menu && window.bootstrap) {
    menu.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        menu.addEventListener('hidden.bs.offcanvas', function () {
          target.setAttribute('tabindex', '-1');
          target.focus({ preventScroll: true });
          target.scrollIntoView();
          window.history.replaceState(null, '', link.getAttribute('href'));
        }, { once: true });
        bootstrap.Offcanvas.getOrCreateInstance(menu).hide();
      });
    });
    // Lepaskan backdrop jika pengguna berpindah ke ukuran desktop.
    window.matchMedia('(min-width: 1200px)').addEventListener('change', function (event) {
      if (event.matches) bootstrap.Offcanvas.getInstance(menu)?.hide();
    });
  }

  // Animasi hanya pada elemen anak agar bagian panjang tidak tersembunyi seluruhnya.
  const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');

  const backToTop = document.querySelector('.back-to-top');
  if (backToTop) {
    const updateBackToTop = function () {
      backToTop.classList.toggle('is-visible', window.scrollY > 300);
    };
    window.addEventListener('scroll', updateBackToTop, { passive: true });
    updateBackToTop();
    backToTop.addEventListener('click', function () {
      document.querySelector('.navbar-brand')?.focus({ preventScroll: true });
      window.scrollTo({ top: 0, behavior: motionPreference.matches ? 'instant' : 'smooth' });
    });
  }

  // Hitung statistik dari nol saat blok statistik pertama kali terlihat.
  const heroStats = document.querySelector('.hero-stats');
  const statValues = heroStats ? Array.from(heroStats.querySelectorAll('strong')).map(function (element) {
    const match = element.textContent.trim().match(/^([\d.]+)(.*)$/);
    if (!match) return null;
    return { element: element, target: Number(match[1].replace(/\./g, '')), suffix: match[2] };
  }).filter(Boolean) : [];
  const showFinalStats = function () {
    statValues.forEach(function (stat) {
      stat.element.textContent = stat.target.toLocaleString('id-ID') + stat.suffix;
    });
  };
  const animateStats = function () {
    const startTime = performance.now();
    const duration = 1500;
    const tick = function (currentTime) {
      const progress = Math.min((currentTime - startTime) / duration, 1);
      const easedProgress = 1 - Math.pow(1 - progress, 3);
      statValues.forEach(function (stat) {
        const value = Math.floor(stat.target * easedProgress);
        stat.element.textContent = value.toLocaleString('id-ID') + stat.suffix;
      });
      if (progress < 1) window.requestAnimationFrame(tick);
    };
    window.requestAnimationFrame(tick);
  };
  if (statValues.length) {
    statValues.forEach(function (stat) { stat.element.textContent = '0' + stat.suffix; });
    if (motionPreference.matches || !('IntersectionObserver' in window)) showFinalStats();
    else {
      const statsObserver = new IntersectionObserver(function (entries, observer) {
        if (!entries.some(function (entry) { return entry.isIntersecting; })) return;
        animateStats();
        observer.disconnect();
      }, { threshold: 0.35 });
      statsObserver.observe(heroStats);
    }
  }

  if ('IntersectionObserver' in window && !motionPreference.matches) {
    const revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.remove('reveal-pending');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });
    document.querySelectorAll('.reveal:not(section)').forEach(function (element) {
      if (element.closest('#home') || element.querySelector('.reveal')) return;
      element.classList.add('reveal-pending');
      revealObserver.observe(element);
    });
    // Navigasi keyboard langsung membuka konten yang menerima fokus.
    document.addEventListener('focusin', function (event) {
      event.target.closest('.reveal-pending')?.classList.remove('reveal-pending');
    });
    motionPreference.addEventListener('change', function (event) {
      if (event.matches) {
        revealObserver.disconnect();
        document.querySelectorAll('.reveal-pending').forEach(function (el) { el.classList.remove('reveal-pending'); });
      }
    });
  }

  // Browser memuat foto di luar hero saat mendekati viewport; efek muncul setelah selesai dimuat.
  document.querySelectorAll('main img').forEach(function (img) {
    if (!img.closest('.heroSwiper')) img.loading = 'lazy';
    img.decoding = 'async';
    const finish = function () {
      img.classList.remove('image-loading');
      img.classList.add('image-ready');
    };
    img.addEventListener('load', finish, { once: true });
    img.addEventListener('error', finish, { once: true });
    if (img.complete) finish();
    else img.classList.add('image-loading');
  });

  // Slider hero berjalan otomatis dengan fade, zoom halus, dan dukungan gerak minimum.
  const hero = document.querySelector('.heroSwiper');
  if (window.Swiper && hero) {
    const slider = new Swiper(hero, {
      effect: 'fade', fadeEffect: { crossFade: true },
      speed: motionPreference.matches ? 0 : 1100,
      loop: true,
      autoplay: { delay: 6200, disableOnInteraction: false, pauseOnMouseEnter: true }
    });
    const heroTitle = document.querySelector('.hero-title');
    const heroSubtitle = document.querySelector('.hero-subtitle');
    let typingTimer = null;
    // Ukur seluruh teks lengkap, termasuk kursor, sebelum animasi dimulai.
    // Satu ukuran font untuk semua slide menjaga tampilan tetap konsisten.
    const fitHeroSubtitles = function () {
      const heroCopy = document.querySelector('.hero-copy');
      if (!heroCopy || !heroTitle || !heroSubtitle) return;
      const slides = Array.from(hero.querySelectorAll('[data-hero-subtitle]'));
      heroSubtitle.style.removeProperty('font-size');
      const baseSize = parseFloat(getComputedStyle(heroSubtitle).fontSize);
      const copyStyle = getComputedStyle(heroCopy);
      const titleStyle = getComputedStyle(heroTitle);
      const width = heroSubtitle.getBoundingClientRect().width;
      const titleProbe = heroTitle.cloneNode(false);
      const probe = heroSubtitle.cloneNode(false);
      [titleProbe, probe].forEach(function (element) {
        element.removeAttribute('aria-live');
        element.setAttribute('aria-hidden', 'true');
        Object.assign(element.style, {
          position: 'absolute', visibility: 'hidden', pointerEvents: 'none',
          width: width + 'px', height: 'auto', minHeight: '0',
          animation: 'none', transition: 'none'
        });
        heroCopy.appendChild(element);
      });
      titleProbe.classList.remove('is-changing');
      probe.classList.add('is-typing');
      let titleHeight = 0;
      slides.forEach(function (slide) {
        titleProbe.textContent = slide.dataset.heroTitle || '';
        titleHeight = Math.max(titleHeight, titleProbe.getBoundingClientRect().height);
      });
      const availableHeight = Math.max(1, heroCopy.clientHeight
        - parseFloat(copyStyle.paddingTop) - parseFloat(copyStyle.paddingBottom)
        - heroCopy.querySelector('.hero-kicker').getBoundingClientRect().height
        - titleHeight - parseFloat(titleStyle.marginTop) - parseFloat(titleStyle.marginBottom) - 4);
      let fittedSize = baseSize;
      slides.forEach(function (slide) {
        const text = slide.dataset.heroSubtitle || '';
        const characters = Array.from(text).length;
        // Perkiraan luas karakter mempersempit pencarian; pengukuran DOM memastikan teks muat.
        const estimate = Math.sqrt(width * availableHeight / Math.max(1, characters * .56));
        let low = 1;
        let high = fittedSize;
        let candidate = Math.min(high, Math.max(low, estimate));
        probe.textContent = text;
        while (high - low > .25) {
          probe.style.fontSize = candidate + 'px';
          if (probe.getBoundingClientRect().height <= availableHeight && probe.scrollWidth <= Math.ceil(width)) low = candidate;
          else high = candidate;
          candidate = (low + high) / 2;
        }
        fittedSize = low;
      });
      titleProbe.remove();
      probe.remove();
      heroSubtitle.style.fontSize = fittedSize + 'px';
    };
    const updateHeroCopy = function () {
      const activeSlide = hero.querySelector('.swiper-slide-active');
      if (!activeSlide || !heroTitle || !heroSubtitle) return;
      const title = activeSlide.dataset.heroTitle || '';
      const subtitle = activeSlide.dataset.heroSubtitle || '';
      window.clearInterval(typingTimer);
      heroTitle.classList.remove('is-changing');
      void heroTitle.offsetWidth;
      heroTitle.textContent = title;
      heroTitle.classList.add('is-changing');
      heroSubtitle.classList.remove('is-typing');
      if (motionPreference.matches) {
        heroSubtitle.textContent = subtitle;
        return;
      }
      heroSubtitle.textContent = '';
      let characterIndex = 0;
      typingTimer = window.setInterval(function () {
        heroSubtitle.textContent = subtitle.slice(0, characterIndex + 1);
        characterIndex += 1;
        if (characterIndex >= subtitle.length) {
          window.clearInterval(typingTimer);
          heroSubtitle.classList.add('is-typing');
        }
      }, 78);
    };
    const syncPlayback = function () {
      const stopped = motionPreference.matches || document.hidden;
      if (stopped) slider.autoplay.stop();
      else slider.autoplay.start();
    };
    document.addEventListener('visibilitychange', syncPlayback);
    motionPreference.addEventListener('change', function () {
      slider.params.speed = motionPreference.matches ? 0 : 1100;
      syncPlayback();
    });
    // Cegah foto pada slide tersembunyi ikut menerima fokus keyboard.
    const syncSlides = function () {
      hero.querySelectorAll('.swiper-slide').forEach(function (slide) {
        slide.inert = !slide.classList.contains('swiper-slide-active');
      });
    };
    slider.on('slideChangeTransitionStart', syncSlides);
    slider.on('slideChangeTransitionStart', updateHeroCopy);
    syncSlides();
    fitHeroSubtitles();
    updateHeroCopy();
    if (document.fonts) document.fonts.ready.then(fitHeroSubtitles);
    let resizeFrame = null;
    window.addEventListener('resize', function () {
      window.cancelAnimationFrame(resizeFrame);
      resizeFrame = window.requestAnimationFrame(fitHeroSubtitles);
    });
    syncPlayback();
  }

  // Kartu guru dapat digeser; kisi CSS tetap tersedia jika Swiper gagal dimuat.
  const teacherGallery = document.querySelector('.teacherSwiper');
  const initTeacherSlider = function () {
    if (!window.Swiper || !teacherGallery) return;
    new Swiper(teacherGallery, {
      slidesPerView: 2,
      spaceBetween: 12,
      pagination: { el: '.teacherSwiper .swiper-pagination', clickable: true },
      a11y: { paginationBulletMessage: 'Buka kelompok guru {{index}}' },
      breakpoints: { 768: { slidesPerView: 2, spaceBetween: 20 }, 992: { slidesPerView: 3, spaceBetween: 20 }, 1200: { slidesPerView: 4, spaceBetween: 20 } }
    });
  };
  initTeacherSlider();

  // Popover tersedia hanya ketika teks mobile benar-benar terpotong.
  function initClippedPopovers(gallerySelector, fieldSelector) {
    const gallery = document.querySelector(gallerySelector);
    if (!gallery || !window.bootstrap?.Popover) return;
    const mobileLayout = window.matchMedia('(max-width: 767.98px)');
    const fields = gallery.querySelectorAll(fieldSelector);
    const popovers = new Map();
    const closePopovers = function () {
      popovers.forEach(function (popover) { popover.hide(); });
    };
    const updatePopovers = function () {
      fields.forEach(function (field) {
        const clipped = mobileLayout.matches && (field.scrollWidth > field.clientWidth || field.scrollHeight > field.clientHeight + 1);
        if (clipped && !popovers.has(field)) {
          field.setAttribute('tabindex', '0');
          popovers.set(field, new bootstrap.Popover(field, {
            content: function () { return field.textContent.trim(); },
            trigger: 'click',
            placement: 'top',
            container: 'body',
            customClass: 'clipped-text-popover',
            animation: false
          }));
        } else if (!clipped && popovers.has(field)) {
          popovers.get(field).dispose();
          popovers.delete(field);
          field.removeAttribute('tabindex');
        }
      });
    };
    fields.forEach(function (field) {
      field.addEventListener('show.bs.popover', closePopovers);
      field.addEventListener('blur', closePopovers);
      field.addEventListener('keydown', function (event) {
        if ((event.key === 'Enter' || event.key === ' ') && popovers.has(field)) {
          event.preventDefault();
          popovers.get(field).toggle();
        }
      });
    });
    document.addEventListener('pointerdown', function (event) {
      if (!Array.from(fields).some(function (field) { return field.contains(event.target); }) && !event.target.closest('.clipped-text-popover')) closePopovers();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closePopovers();
    });
    gallery.swiper?.on('slideChange', closePopovers);
    window.addEventListener('resize', closePopovers);
    const resizeObserver = new ResizeObserver(updatePopovers);
    fields.forEach(function (field) { resizeObserver.observe(field); });
    document.fonts.ready.then(updatePopovers);
    updatePopovers();
  }

  initClippedPopovers('.teacherSwiper', '.teacher-body h3, .teacher-body p, .teacher-body span');

  // Galeri menampilkan beberapa foto per layar dan tetap dapat digeser.
  if (window.Swiper && document.querySelector('.gallerySwiper')) {
    new Swiper('.gallerySwiper', {
      slidesPerView: 2,
      spaceBetween: 16,
      pagination: { el: '.gallerySwiper .swiper-pagination', clickable: true },
      a11y: { paginationBulletMessage: 'Buka halaman galeri {{index}}' },
      breakpoints: { 576: { slidesPerView: 3 }, 1200: { slidesPerView: 4 } }
    });
  }

  // Testimonial menampilkan beberapa card per layar dan dapat digeser.
  if (window.Swiper && document.querySelector('.testimonialSwiper')) {
    new Swiper('.testimonialSwiper', {
      slidesPerView: 2,
      spaceBetween: 16,
      pagination: { el: '.testimonialSwiper .swiper-pagination', clickable: true },
      a11y: { paginationBulletMessage: 'Buka testimonial {{index}}' },
      breakpoints: { 576: { slidesPerView: 3 }, 1200: { slidesPerView: 4 } }
    });
  }

  initClippedPopovers('.testimonialSwiper', '.testimonial-body h3, .testimonial-body p');

  // Fasilitas menampilkan beberapa card per layar dan dapat digeser.
  if (window.Swiper && document.querySelector('.facilitySwiper')) {
    new Swiper('.facilitySwiper', {
      slidesPerView: 2,
      spaceBetween: 16,
      pagination: { el: '.facilitySwiper .swiper-pagination', clickable: true },
      a11y: { paginationBulletMessage: 'Buka fasilitas {{index}}' },
      breakpoints: { 576: { slidesPerView: 3 }, 1200: { slidesPerView: 4 } }
    });
  }

  initClippedPopovers('.facilitySwiper', '.facility-body h3, .facility-body p');

  // Berita menampilkan beberapa card per layar dan dapat digeser.
  if (window.Swiper && document.querySelector('.newsSwiper')) {
    new Swiper('.newsSwiper', {
      slidesPerView: 1,
      spaceBetween: 16,
      pagination: { el: '.newsSwiper .swiper-pagination', clickable: true },
      a11y: { paginationBulletMessage: 'Buka berita {{index}}' },
      breakpoints: { 576: { slidesPerView: 2 }, 1200: { slidesPerView: 3 } }
    });
  }

  // Video baru dimuat setelah dipilih dan dihentikan saat modal ditutup.
  const videoGallery = document.querySelector('.videoSwiper');
  if (videoGallery && videoGallery.querySelector('.swiper-slide')) {
    if (window.Swiper) {
      new Swiper(videoGallery, {
        slidesPerView: 1, spaceBetween: 20, watchOverflow: true,
        pagination: { el: '.videoSwiper .swiper-pagination', clickable: true },
        a11y: { paginationBulletMessage: 'Buka kelompok video {{index}}' },
        breakpoints: { 640: { slidesPerView: 2 }, 1200: { slidesPerView: 3 } }
      });
    }
  }
  const videoModalElement = document.getElementById('videoModal');
  if (videoGallery && videoModalElement && window.bootstrap) {
    const videoModal = bootstrap.Modal.getOrCreateInstance(videoModalElement);
    const videoPlayer = document.getElementById('videoPlayer');
    let videoTrigger = null;
    videoGallery.querySelectorAll('.video-play').forEach(function (link) {
      link.setAttribute('aria-haspopup', 'dialog');
      link.setAttribute('aria-controls', 'videoModal');
    });
    videoGallery.addEventListener('click', function (event) {
      const link = event.target.closest('.video-play');
      if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const videoId = link.dataset.youtubeId || '';
      if (!/^[a-zA-Z0-9_-]{11}$/.test(videoId)) return;
      event.preventDefault();
      videoTrigger = link;
      const card = link.closest('.video-card');
      const title = card.querySelector('h3').textContent + ' â€” ' + card.querySelector('.video-card-body p').textContent;
      document.getElementById('videoModalTitle').textContent = title;
      document.getElementById('videoYoutubeLink').href = link.href;
      const iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1&rel=0';
      iframe.title = title;
      iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      iframe.allowFullscreen = true;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      videoPlayer.replaceChildren(iframe);
      videoModal.show();
    });
    videoModalElement.addEventListener('hide.bs.modal', function () { videoPlayer.replaceChildren(); });
    videoModalElement.addEventListener('hidden.bs.modal', function () { videoTrigger?.focus({ preventScroll: true }); });
  }

})();
