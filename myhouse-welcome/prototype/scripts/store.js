/* =============================================================================
   MyHouse Welcome — prototype state
   Persists to localStorage so the prototype is connected end to end: what you
   type in onboarding shows up in the dashboard, the live preview and the guest
   guide. In the real product this is the API described in docs/16.
   ============================================================================= */

(function (global) {
  'use strict';

  var KEY = 'mhw.prototype.v1';

  /* Entitlements are read from the same package definition the real product
     uses. Nothing in this prototype asks "which package is this?" - it asks
     the entitlement, exactly as docs/05 requires. */
  var PACKAGES = {
    essential: {
      key: 'essential', name: 'Essential', amountCents: 4900,
      tagline: 'Everything a guest needs to arrive and settle in.',
      features: {
        'welcome.properties.max': 1,
        'welcome.languages.max': 1,
        'welcome.languages.auto_translate': false,
        'welcome.sections.services': false,
        'welcome.sections.appliances': false,
        'welcome.sections.waste': false,
        'welcome.sections.transport': false,
        'welcome.sections.recommendations': false,
        'welcome.sections.experiences': false,
        'welcome.sections.extras': false,
        'welcome.sections.custom.max': 0,
        'welcome.media.images.max': 15,
        'welcome.media.storage_mb': 50,
        'welcome.media.pdf': false,
        'welcome.media.video_embed': false,
        'welcome.recommendations.max': 0,
        'welcome.branding.colors': false,
        'welcome.branding.remove_powered_by': false,
        'welcome.guide.search': false,
        'welcome.guide.protected_sections': false,
        'welcome.analytics.basic': true,
        'welcome.analytics.advanced': false,
        'welcome.qr.branded_card': false
      }
    },
    plus: {
      key: 'plus', name: 'Plus', amountCents: 9900, recommended: true,
      tagline: "The whole house explained, in your guests' languages.",
      features: {
        'welcome.properties.max': 1,
        'welcome.languages.max': 5,
        'welcome.languages.auto_translate': false,
        'welcome.sections.services': true,
        'welcome.sections.appliances': true,
        'welcome.sections.waste': true,
        'welcome.sections.transport': true,
        'welcome.sections.recommendations': true,
        'welcome.sections.experiences': false,
        'welcome.sections.extras': false,
        'welcome.sections.custom.max': 3,
        'welcome.media.images.max': 80,
        'welcome.media.storage_mb': 400,
        'welcome.media.pdf': true,
        'welcome.media.video_embed': false,
        'welcome.recommendations.max': 40,
        'welcome.branding.colors': true,
        'welcome.branding.remove_powered_by': false,
        'welcome.guide.search': true,
        'welcome.guide.protected_sections': false,
        'welcome.analytics.basic': true,
        'welcome.analytics.advanced': false,
        'welcome.qr.branded_card': false
      }
    },
    pro: {
      key: 'pro', name: 'Pro', amountCents: 17900,
      tagline: 'For hosts who do this for a living.',
      features: {
        'welcome.properties.max': 5,
        'welcome.languages.max': 20,
        'welcome.languages.auto_translate': true,
        'welcome.sections.services': true,
        'welcome.sections.appliances': true,
        'welcome.sections.waste': true,
        'welcome.sections.transport': true,
        'welcome.sections.recommendations': true,
        'welcome.sections.experiences': true,
        'welcome.sections.extras': true,
        'welcome.sections.custom.max': 20,
        'welcome.media.images.max': 400,
        'welcome.media.storage_mb': 2000,
        'welcome.media.pdf': true,
        'welcome.media.video_embed': true,
        'welcome.recommendations.max': 200,
        'welcome.branding.colors': true,
        'welcome.branding.remove_powered_by': true,
        'welcome.guide.search': true,
        'welcome.guide.protected_sections': true,
        'welcome.analytics.basic': true,
        'welcome.analytics.advanced': true,
        'welcome.qr.branded_card': true
      }
    }
  };

  /* Default section order is by WHEN A GUEST NEEDS IT - arrival first,
     departure last. Not alphabetical, not logical-for-us. See docs/03. */
  var DEFAULT_SECTIONS = [
    { key: 'welcome',    title: 'Welcome',            icon: '\u{1F44B}', enabled: true },
    { key: 'checkin',    title: 'Check-in',           icon: '\u{1F511}', enabled: true },
    { key: 'wifi',       title: 'Wi-Fi',              icon: '\u{1F4F6}', enabled: true },
    { key: 'arrival',    title: 'How to arrive',      icon: '\u{1F697}', enabled: true },
    { key: 'parking',    title: 'Parking',            icon: '\u{1F17F}', enabled: true },
    { key: 'services',   title: 'Services',           icon: '\u{1F6CB}', enabled: true,  gate: 'welcome.sections.services' },
    { key: 'appliances', title: 'Appliances',         icon: '\u{1F50C}', enabled: true,  gate: 'welcome.sections.appliances' },
    { key: 'rules',      title: 'House rules',        icon: '\u{1F4CB}', enabled: true },
    { key: 'waste',      title: 'Waste & recycling',  icon: '\u{267B}',  enabled: true,  gate: 'welcome.sections.waste' },
    { key: 'eat',        title: 'Where to eat',       icon: '\u{1F37D}', enabled: true,  gate: 'welcome.sections.recommendations' },
    { key: 'experiences',title: 'Experiences',        icon: '\u{1F39F}', enabled: false, gate: 'welcome.sections.experiences' },
    { key: 'checkout',   title: 'Check-out',          icon: '\u{1F44B}', enabled: true },
    { key: 'emergency',  title: 'Emergency',          icon: '\u{1F6A8}', enabled: true }
  ];

  function defaults() {
    return {
      packageKey: 'plus',
      order: null,
      account: { name: 'Marco Bianchi', email: 'marco@example.com' },
      guide: {
        status: 'draft',
        hasUnpublishedChanges: false,
        slug: 'casa-san-francesco',
        publishedAt: null,
        version: 0,
        defaultLocale: 'it-IT',
        locales: ['it-IT', 'en-GB'],
        qrToken: '7f3a9c2b'
      },
      onboarding: { currentStep: 'property', completed: [], skipped: [] },
      content: {
        name: '', propertyType: 'apartment', city: '', address: '',
        phone: '', whatsapp: '', hostName: '', welcomeMessage: '',
        checkinTime: '15:00', selfCheckin: true, keyLocation: 'lockbox',
        accessInstructions: '', doorCode: '',
        hasWifi: true, ssid: '', wifiPassword: '', wifiInstructions: '',
        hasParking: true, parkingType: 'street', parkingInstructions: '',
        byCar: '', byTrain: '',
        amenities: [], rules: { smoking: 'not_allowed', pets: 'ask', parties: 'not_allowed' },
        quietHours: '', checkoutTime: '10:00', keyReturn: '',
        emergencyNumber: '112', hostEmergency: '',
        places: []
      },
      sections: null,
      stats: { visits: 125, qrShare: 71 },
      impersonating: false
    };
  }

  function read() {
    try {
      var raw = global.localStorage.getItem(KEY);
      if (!raw) return defaults();
      var parsed = JSON.parse(raw);
      var base = defaults();
      // shallow merge so a new field added here does not break a saved state
      Object.keys(base).forEach(function (k) {
        if (parsed[k] === undefined) parsed[k] = base[k];
      });
      Object.keys(base.content).forEach(function (k) {
        if (parsed.content[k] === undefined) parsed.content[k] = base.content[k];
      });
      return parsed;
    } catch (e) {
      // private browsing, cleared site data, blocked storage - never throw
      return defaults();
    }
  }

  function write(state) {
    try { global.localStorage.setItem(KEY, JSON.stringify(state)); }
    catch (e) { /* the prototype still works in memory */ }
    return state;
  }

  var Store = {
    PACKAGES: PACKAGES,
    DEFAULT_SECTIONS: DEFAULT_SECTIONS,

    get: read,

    set: function (patch) {
      var s = read();
      Object.keys(patch).forEach(function (k) { s[k] = patch[k]; });
      write(s);
      global.dispatchEvent(new CustomEvent('mhw:change', { detail: s }));
      return s;
    },

    patchContent: function (patch) {
      var s = read();
      Object.keys(patch).forEach(function (k) { s.content[k] = patch[k]; });
      if (s.guide.status === 'published') s.guide.hasUnpublishedChanges = true;
      write(s);
      global.dispatchEvent(new CustomEvent('mhw:change', { detail: s }));
      return s;
    },

    reset: function () {
      try { global.localStorage.removeItem(KEY); } catch (e) {}
      return defaults();
    },

    /* ---- entitlements: the only way anything asks what is available ------ */

    pkg: function () { return PACKAGES[read().packageKey] || PACKAGES.plus; },

    can: function (featureKey) {
      var v = this.pkg().features[featureKey];
      return v === true || (typeof v === 'number' && v !== 0);
    },

    limit: function (featureKey) {
      var v = this.pkg().features[featureKey];
      return typeof v === 'number' ? v : 0;
    },

    /** Which package would grant this feature? Drives "Available with Plus". */
    requiredPackage: function (featureKey) {
      var tiers = ['essential', 'plus', 'pro'];
      for (var i = 0; i < tiers.length; i++) {
        var v = PACKAGES[tiers[i]].features[featureKey];
        if (v === true || (typeof v === 'number' && v > 0)) return PACKAGES[tiers[i]].name;
      }
      return 'Pro';
    },

    /* ---- sections ------------------------------------------------------- */

    sections: function () {
      var s = read();
      var list = s.sections || DEFAULT_SECTIONS.map(function (x) {
        return { key: x.key, title: x.title, icon: x.icon, enabled: x.enabled, gate: x.gate };
      });
      return list;
    },

    saveSections: function (list) { return this.set({ sections: list }); },

    /** Sections a guest actually sees: enabled AND entitled AND non-empty. */
    visibleSections: function () {
      var self = this;
      return this.sections().filter(function (sec) {
        if (!sec.enabled) return false;
        if (sec.gate && !self.can(sec.gate)) return false;
        return true;
      });
    },

    /* ---- completion -----------------------------------------------------
       Weighted by guest impact, not by field count. A section answered "no"
       counts as complete - answering is completing. See docs/07.            */

    completion: function () {
      var c = read().content;
      var self = this;
      var checks = [
        { w: 3, done: !!c.name },
        { w: 3, done: !!c.checkinTime },
        { w: 3, done: c.hasWifi === false || (!!c.ssid && !!c.wifiPassword) },
        { w: 3, done: !!c.address },
        { w: 3, done: !!c.accessInstructions || c.selfCheckin === false },
        { w: 2, done: !!c.welcomeMessage },
        { w: 2, done: !!c.hostName },
        { w: 2, done: !!c.phone },
        { w: 2, done: c.hasParking === false || !!c.parkingInstructions },
        { w: 2, done: !!c.byCar || !!c.byTrain },
        { w: 2, done: !!c.checkoutTime && !!c.keyReturn },
        { w: 2, done: !!c.emergencyNumber },
        { w: 2, done: !!c.quietHours },
        { w: 1, done: (c.amenities || []).length > 0, gate: 'welcome.sections.services' },
        { w: 1, done: (c.places || []).length > 0, gate: 'welcome.sections.recommendations' }
      ];
      var total = 0, got = 0;
      checks.forEach(function (chk) {
        // sections the package does not include are excluded from the
        // denominator, so an Essential guide can legitimately reach 100%
        if (chk.gate && !self.can(chk.gate)) return;
        total += chk.w;
        if (chk.done) got += chk.w;
      });
      return total === 0 ? 0 : Math.round((got / total) * 100);
    },

    /** The highest-impact incomplete items, ranked by guest need. */
    missing: function () {
      var c = read().content;
      var out = [];
      if (!c.name) out.push({ label: 'Name your property', step: 'property' });
      if (c.hasWifi && !c.wifiPassword) out.push({ label: 'Add your Wi-Fi password', step: 'wifi' });
      if (c.selfCheckin && !c.accessInstructions) out.push({ label: 'Explain how guests get in', step: 'checkin' });
      if (!c.address) out.push({ label: 'Add your address', step: 'property' });
      if (!c.welcomeMessage) out.push({ label: 'Write a welcome message', step: 'welcome' });
      if (!c.keyReturn) out.push({ label: 'Say what to do with the keys', step: 'checkout' });
      if (!c.phone) out.push({ label: 'Add a phone number', step: 'property' });
      return out.slice(0, 3);
    },

    publish: function () {
      var s = read();
      s.guide.status = 'published';
      s.guide.hasUnpublishedChanges = false;
      s.guide.version += 1;
      s.guide.publishedAt = new Date().toISOString();
      write(s);
      global.dispatchEvent(new CustomEvent('mhw:change', { detail: s }));
      return s;
    },

    /** What publishing would leave out, and why. See docs/05. */
    excluded: function () {
      var self = this;
      var out = [];
      this.sections().forEach(function (sec) {
        if (sec.enabled && sec.gate && !self.can(sec.gate)) {
          out.push(sec.title + ' — available with ' + self.requiredPackage(sec.gate));
        }
      });
      var max = this.limit('welcome.languages.max');
      var locales = read().guide.locales;
      if (locales.length > max) {
        out.push((locales.length - max) + ' extra language—your package includes ' + max);
      }
      return out;
    },

    guideUrl: function () { return 'welcome.myhouse.it/' + read().guide.slug; },
    qrUrl: function () { return 'mh.li/q/' + read().guide.qrToken; },

    money: function (cents, currency) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency', currency: currency || 'EUR', minimumFractionDigits: 0
      }).format(cents / 100);
    }
  };

  global.MHW = Store;
})(window);
