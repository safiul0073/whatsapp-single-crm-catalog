import Alpine from "alpinejs";

Alpine.data("globalSearch", (config) => ({
  query: "",
  groups: [],
  loading: false,
  activeIndex: -1,
  totalResults: 0,
  debounceTimer: null,
  searchUrl: config.url || "",

  init() {
    this.$watch("query", (value) => {
      this.debouncedSearch(value);
    });
  },

  debouncedSearch(value) {
    clearTimeout(this.debounceTimer);

    if (value.length < 2) {
      this.groups = [];
      this.totalResults = 0;
      this.activeIndex = -1;
      return;
    }

    this.debounceTimer = setTimeout(() => {
      this.fetchResults(value);
    }, 300);
  },

  async fetchResults(query) {
    this.loading = true;
    this.activeIndex = -1;

    try {
      const url = new URL(this.searchUrl, window.location.origin);
      url.searchParams.set("q", query);

      const response = await fetch(url.toString(), {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        this.groups = [];
        this.totalResults = 0;
        return;
      }

      const data = await response.json();
      this.groups = data.groups || [];
      this.totalResults = this.groups.reduce(
        (sum, g) => sum + g.results.length,
        0,
      );
    } catch {
      this.groups = [];
      this.totalResults = 0;
    } finally {
      this.loading = false;
    }
  },

  /**
   * Get a flat list of all result items for keyboard navigation.
   */
  get flatResults() {
    const items = [];
    for (const group of this.groups) {
      for (const result of group.results) {
        items.push(result);
      }
    }
    return items;
  },

  onKeydown(event) {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      if (this.activeIndex < this.flatResults.length - 1) {
        this.activeIndex++;
      } else {
        this.activeIndex = 0;
      }
      this.scrollToActive();
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      if (this.activeIndex > 0) {
        this.activeIndex--;
      } else {
        this.activeIndex = this.flatResults.length - 1;
      }
      this.scrollToActive();
    } else if (event.key === "Enter") {
      event.preventDefault();
      const item = this.flatResults[this.activeIndex];
      if (item) {
        window.location.href = item.url;
      }
    }
  },

  scrollToActive() {
    this.$nextTick(() => {
      const activeEl = this.$root.querySelector(
        '[data-search-index="' + this.activeIndex + '"]',
      );
      if (activeEl) {
        activeEl.scrollIntoView({ block: "nearest" });
      }
    });
  },

  /**
   * Get the flat index for a result within a group.
   */
  getFlatIndex(groupIndex, resultIndex) {
    let index = 0;
    for (let g = 0; g < groupIndex; g++) {
      index += this.groups[g].results.length;
    }
    return index + resultIndex;
  },

  clear() {
    this.query = "";
    this.groups = [];
    this.totalResults = 0;
    this.activeIndex = -1;
  },
}));
