/**
 * Komponen Alpine yang membungkus library berat. Setiap library di-import
 * dinamis di dalam init() sehingga hanya diunduh di halaman yang memakainya.
 *
 * Instance library (editor, chart, PDF, dsb.) disimpan di variabel closure, BUKAN di properti
 * komponen: Alpine membungkus properti dengan Proxy reaktif, dan itu merusak field privat (#x)
 * milik pdf.js, Tiptap, dan Chart.js ("Cannot read private member").
 */
export function registerLazyComponents(Alpine) {
    Alpine.data('tomSelect', (options = {}) => {
        let instance = null;

        return {
            async init() {
                const [{ default: TomSelect }] = await Promise.all([
                    import('tom-select'),
                    import('../../css/vendor/tom-select.css'),
                ]);

                instance = new TomSelect(this.$refs.select, {
                    plugins: options.multiple ? ['remove_button'] : [],
                    allowEmptyOption: true,
                    maxOptions: 200,
                    render: {
                        no_results: () => `<div class="no-results">${options.noResults ?? 'Tidak ada hasil'}</div>`,
                    },
                    ...options,
                });
            },

            destroy() {
                instance?.destroy();
            },
        };
    });

    Alpine.data('datePicker', (options = {}) => {
        let instance = null;

        return {
            async init() {
                const [{ default: flatpickr }, { Indonesian }] = await Promise.all([
                    import('flatpickr'),
                    import('flatpickr/dist/l10n/id.js'),
                    import('../../css/vendor/flatpickr.css'),
                ]);

                instance = flatpickr(this.$refs.input, {
                    locale: Indonesian,
                    enableTime: options.time ?? false,
                    time_24hr: true,
                    altInput: true,
                    altFormat: options.time ? 'j F Y, H:i' : 'j F Y',
                    dateFormat: options.time ? 'Y-m-d H:i' : 'Y-m-d',
                    allowInput: false,
                    onChange: () => this.$refs.input.dispatchEvent(new Event('input', { bubbles: true })),
                    ...options.flatpickr,
                });
            },

            clear() {
                instance?.clear();
            },

            destroy() {
                instance?.destroy();
            },
        };
    });

    Alpine.data('richEditor', (options = {}) => {
        let editor = null;

        return {
            active: {},

            async init() {
                const [{ Editor }, { default: StarterKit }] = await Promise.all([
                    import('@tiptap/core'),
                    import('@tiptap/starter-kit'),
                    import('../../css/vendor/editor.css'),
                ]);

                const field = this.$refs.field;

                editor = new Editor({
                    element: this.$refs.surface,
                    extensions: [StarterKit],
                    content: field.value,
                    editorProps: {
                        attributes: {
                            class: 'prose-content min-h-32 px-3 py-2 focus:outline-none',
                            'aria-label': options.label ?? 'Editor teks',
                            role: 'textbox',
                            'aria-multiline': 'true',
                        },
                    },
                    onUpdate: ({ editor: e }) => {
                        field.value = e.isEmpty ? '' : e.getHTML();
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        this.refresh();
                    },
                    onSelectionUpdate: () => this.refresh(),
                });

                this.refresh();
            },

            refresh() {
                if (!editor) {
                    return;
                }

                this.active = {
                    bold: editor.isActive('bold'),
                    italic: editor.isActive('italic'),
                    h2: editor.isActive('heading', { level: 2 }),
                    bullet: editor.isActive('bulletList'),
                    ordered: editor.isActive('orderedList'),
                };
            },

            run(command) {
                if (!editor) {
                    return;
                }

                const chain = editor.chain().focus();

                ({
                    bold: () => chain.toggleBold(),
                    italic: () => chain.toggleItalic(),
                    h2: () => chain.toggleHeading({ level: 2 }),
                    bullet: () => chain.toggleBulletList(),
                    ordered: () => chain.toggleOrderedList(),
                })[command]().run();
            },

            destroy() {
                editor?.destroy();
            },
        };
    });

    Alpine.data('filePicker', (options = {}) => {
        let pond = null;

        return {
            async init() {
                const [
                    { create, registerPlugin },
                    { default: typePlugin },
                    { default: sizePlugin },
                    { default: id },
                ] = await Promise.all([
                    import('filepond'),
                    import('filepond-plugin-file-validate-type'),
                    import('filepond-plugin-file-validate-size'),
                    import('./filepond-id'),
                    import('../../css/vendor/filepond.css'),
                ]);

                registerPlugin(typePlugin, sizePlugin);

                // Livewire meminta daftar dikosongkan setelah berkas diproses (mis. lampiran sudah tersimpan).
                window.addEventListener('filepond-clear', () => pond?.removeFiles());

                const livewireProp = options.livewireProp;

                pond = create(this.$refs.input, {
                    ...id,
                    allowMultiple: options.multiple ?? false,
                    acceptedFileTypes: options.accept ?? null,
                    maxFileSize: options.maxSize ?? '50MB',
                    credits: false,
                    // Di dalam komponen Livewire, unggah lewat $wire.upload (file sementara).
                    server: livewireProp
                        ? {
                              process: (fieldName, file, metadata, load, error, progress) => {
                                  this.$wire.upload(
                                      livewireProp,
                                      file,
                                      (name) => load(name),
                                      () => error('Gagal mengunggah'),
                                      (event) => progress(true, event.detail.progress, 100),
                                  );
                              },
                              revert: (name, load) => {
                                  this.$wire.removeUpload(livewireProp, name, load);
                              },
                          }
                        : null,
                });
            },

            destroy() {
                pond?.destroy();
            },
        };
    });

    Alpine.data('pdfViewer', ({ url }) => {
        let doc = null;
        let task = null;

        return {
            page: 1,
            pages: 0,
            loading: true,
            failed: false,

            async init() {
                try {
                    const pdfjs = await import('pdfjs-dist');
                    const worker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');

                    pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

                    doc = await pdfjs.getDocument({ url }).promise;
                    this.pages = doc.numPages;

                    // Tampilkan kanvas dulu: lebar wadah baru bisa diukur setelah terlihat.
                    this.loading = false;
                    await this.$nextTick();
                    await this.render();
                } catch (error) {
                    console.error('[pdfViewer]', error);
                    this.failed = true;
                } finally {
                    this.loading = false;
                }
            },

            async render() {
                task?.cancel();

                const page = await doc.getPage(this.page);
                const canvas = this.$refs.canvas;
                const width = this.$refs.wrap.clientWidth || 300;
                const base = page.getViewport({ scale: 1 });
                const scale = (width / base.width) * Math.min(window.devicePixelRatio || 1, 2);
                const viewport = page.getViewport({ scale });

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.width = '100%';

                task = page.render({ canvasContext: canvas.getContext('2d'), viewport });

                try {
                    await task.promise;
                } catch (error) {
                    // RenderingCancelledException saat pengguna berpindah halaman dengan cepat.
                    if (error?.name !== 'RenderingCancelledException') {
                        throw error;
                    }
                }
            },

            go(delta) {
                const next = this.page + delta;

                if (next < 1 || next > this.pages) {
                    return;
                }

                this.page = next;
                this.render();
            },

            destroy() {
                task?.cancel();
                doc?.destroy();
            },
        };
    });

    Alpine.data('sortableList', (options = {}) => {
        let instance = null;

        return {
            async init() {
                const { default: Sortable } = await import('sortablejs');

                instance = Sortable.create(this.$el, {
                    animation: 150,
                    handle: options.handle ?? '[data-handle]',
                    ghostClass: 'opacity-40',
                    onEnd: (event) => {
                        const ids = [...this.$el.children].map((el) => el.dataset.id);

                        import('../lib/motion').then(({ pulse }) => pulse(event.item));
                        this.$dispatch('sorted', { ids });
                        options.onSort?.(ids);
                    },
                });
            },

            destroy() {
                instance?.destroy();
            },
        };
    });

    // Pengerjaan kuis. Server adalah acuan waktu (`remaining` dari deadline_at); jawaban dikirim dengan debounce lewat
    // method Livewire Renderless dan dicadangkan di localStorage agar tidak hilang saat koneksi putus atau halaman dimuat ulang.
    Alpine.data('quizAttempt', ({ attemptId, ids, answers = {}, flags = [], remaining = null }) => {
        const storageKey = `quiz-attempt-${attemptId}`;
        let endAt = remaining === null ? null : Date.now() + remaining * 1000;
        let ticker = null;
        let saveTimer = null;
        let retryTimer = null;
        let flushing = false;
        let finishing = false;
        const dirty = new Set();
        const textDelay = 700;

        const readBackup = () => {
            try {
                return JSON.parse(localStorage.getItem(storageKey) ?? 'null');
            } catch {
                return null;
            }
        };

        return {
            index: 0,
            answers: { ...answers },
            flags: [...flags],
            left: remaining,
            state: 'saved', // saved | saving | offline | retrying
            warned: false,
            expired: false,

            init() {
                // Jawaban yang belum sempat terkirim (koneksi putus / tab ditutup) dipulihkan dari cadangan lokal.
                const backup = readBackup();

                if (backup) {
                    for (const id of backup.dirty ?? []) {
                        if (backup.answers && id in backup.answers) {
                            this.answers[id] = backup.answers[id];
                            dirty.add(String(id));
                        }
                    }
                }

                if (dirty.size) {
                    this.flush();
                }

                if (endAt !== null) {
                    this.tick();
                    ticker = setInterval(() => this.tick(), 1000);
                }

            },

            destroy() {
                clearInterval(ticker);
                clearTimeout(saveTimer);
                clearTimeout(retryTimer);
            },

            // Dipanggil dari atribut @online.window / @offline.window pada templat.
            online() {
                this.flush();
            },

            offline() {
                this.state = 'offline';
            },

            // Tab kembali aktif: selaraskan jam dengan server dan kirim yang tertunda.
            visible() {
                if (document.visibilityState === 'visible') {
                    this.flush();
                }
            },

            get current() {
                return ids[this.index];
            },

            get total() {
                return ids.length;
            },

            get answeredCount() {
                return ids.filter((id) => this.isAnswered(id)).length;
            },

            get flaggedCount() {
                return this.flags.length;
            },

            isAnswered(id) {
                const value = this.answers[id];

                return value !== undefined && value !== null && String(value).trim() !== '';
            },

            isFlagged(id) {
                return this.flags.includes(id);
            },

            go(index) {
                this.index = Math.min(Math.max(index, 0), ids.length - 1);
                this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            },

            next() {
                this.go(this.index + 1);
            },

            prev() {
                this.go(this.index - 1);
            },

            // Pilihan langsung disimpan; ketikan menunggu jeda.
            choose(id, value) {
                this.answers[id] = value;
                this.mark(id, 0);
            },

            type(id, value) {
                this.answers[id] = value;
                this.mark(id, textDelay);
            },

            mark(id, delay) {
                dirty.add(String(id));
                this.backup();
                this.state = 'saving';
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => this.flush(), delay);
            },

            backup() {
                try {
                    localStorage.setItem(storageKey, JSON.stringify({ answers: this.answers, dirty: [...dirty], at: Date.now() }));
                } catch {
                    // Penyimpanan penuh/dinonaktifkan: tetap lanjut, server menjadi sumber utama.
                }
            },

            async flush() {
                if (flushing || finishing) {
                    return;
                }

                flushing = true;
                clearTimeout(retryTimer);
                let failed = false;

                try {
                    while (dirty.size) {
                        const id = [...dirty][0];
                        const sent = this.answers[id] ?? '';
                        const result = await this.$wire.saveAnswer(Number(id), sent);

                        if (result?.closed) {
                            this.onExpired();

                            return;
                        }

                        if (result?.remaining != null) {
                            this.sync(result.remaining);
                        }

                        // Jika jawaban berubah lagi selama pengiriman, biarkan tetap kotor agar terkirim ulang.
                        if ((this.answers[id] ?? '') === sent) {
                            dirty.delete(id);
                        }

                        this.backup();
                    }

                    this.state = 'saved';
                } catch {
                    failed = true;
                    this.state = navigator.onLine ? 'retrying' : 'offline';
                    retryTimer = setTimeout(() => this.flush(), 4000);
                } finally {
                    flushing = false;
                }

                // Jawaban yang berubah tepat saat loop selesai tidak boleh tertinggal.
                if (!failed && dirty.size) {
                    this.flush();
                }
            },

            async toggleFlag(id) {
                const flagged = !this.isFlagged(id);

                this.flags = flagged ? [...this.flags, id] : this.flags.filter((f) => f !== id);

                try {
                    await this.$wire.toggleFlag(id, flagged);
                } catch {
                    // Tanda ragu-ragu bersifat bantuan; gagal kirim tidak menghalangi pengerjaan.
                }
            },

            // Sinkronkan jam lokal dengan sisa waktu dari server (menghindari jam perangkat yang salah).
            sync(seconds) {
                if (seconds === null || seconds === undefined) {
                    return;
                }

                endAt = Date.now() + seconds * 1000;
            },

            tick() {
                if (endAt === null) {
                    return;
                }

                this.left = Math.max(0, Math.ceil((endAt - Date.now()) / 1000));

                if (this.left <= 60 && !this.warned) {
                    this.warned = true;
                }

                if (this.left === 0 && !this.expired) {
                    this.expired = true;
                    this.finishNow(true);
                }
            },

            get clock() {
                if (this.left === null) {
                    return '';
                }

                const h = Math.floor(this.left / 3600);
                const m = Math.floor((this.left % 3600) / 60);
                const s = this.left % 60;
                const pad = (n) => String(n).padStart(2, '0');

                return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
            },

            onExpired() {
                this.expired = true;
                finishing = true;
                clearInterval(ticker);

                try {
                    localStorage.removeItem(storageKey);
                } catch {
                    // diabaikan
                }

                this.$wire.finish();
            },

            async finishNow(auto = false) {
                if (finishing) {
                    return;
                }

                // Kirim jawaban terakhir dulu; saat waktu habis server masih memberi masa tenggang singkat.
                clearTimeout(saveTimer);
                await this.flush();

                if (dirty.size && !auto && !navigator.onLine) {
                    this.state = 'offline';

                    return;
                }

                finishing = true;
                clearInterval(ticker);

                try {
                    localStorage.removeItem(storageKey);
                } catch {
                    // diabaikan
                }

                this.$wire.finish();
            },
        };
    });

    // Lipat/bentangkan isi tiap pertemuan (materi, tugas, kuis). Pilihan disimpan per mata kuliah di localStorage
    // perangkat ini; bawaannya semua terbentang. Sengaja TANPA binding reaktif Alpine (x-show, :class, x-text) di dalam
    // daftar yang dirender ulang Livewire: kombinasi itu membuat morph gagal dan daftar menghilang. Keadaan diterapkan
    // langsung ke DOM lewat atribut data-fold-* dan dipulihkan setelah Livewire mengganti elemen (MutationObserver).
    Alpine.data('sectionFold', ({ key }) => {
        let folds = {};
        let observer = null;
        let applying = false;

        const save = () => {
            try {
                localStorage.setItem(key, JSON.stringify(folds));
            } catch {
                // Penyimpanan dinonaktifkan: pilihan tetap berlaku selama halaman terbuka.
            }
        };

        return {
            init() {
                try {
                    folds = JSON.parse(localStorage.getItem(key) ?? '{}') ?? {};
                } catch {
                    folds = {};
                }

                this.apply();

                observer = new MutationObserver(() => {
                    if (!applying) {
                        this.apply();
                    }
                });
                observer.observe(this.$root, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'aria-expanded'] });
            },

            destroy() {
                observer?.disconnect();
            },

            ids() {
                return [...this.$root.querySelectorAll('[data-fold-id]')].map((el) => el.dataset.foldId);
            },

            apply() {
                applying = true;

                const ids = this.ids();

                ids.forEach((id) => {
                    const item = this.$root.querySelector(`[data-fold-id="${id}"]`);
                    const folded = !!folds[id];

                    item.querySelector('[data-fold-body]')?.toggleAttribute('hidden', folded);
                    item.querySelector('[data-fold-count]')?.toggleAttribute('hidden', !folded);
                    item.querySelector('[data-fold-header]')?.classList.toggle('border-b', !folded);
                    item.querySelector('[data-fold-toggle]')?.setAttribute('aria-expanded', folded ? 'false' : 'true');
                });

                const all = ids.length > 0 && ids.every((id) => folds[id]);

                this.$root.querySelector('[data-fold-label="fold"]')?.toggleAttribute('hidden', all);
                this.$root.querySelector('[data-fold-label="unfold"]')?.toggleAttribute('hidden', !all);

                // Atribut yang baru kita ubah memicu observer sekali lagi; lepas penjaga setelahnya.
                queueMicrotask(() => setTimeout(() => (applying = false), 0));
            },

            toggle(id) {
                folds[id] = !folds[id];
                save();
                this.apply();
            },

            toggleAll() {
                const ids = this.ids();
                const value = !(ids.length > 0 && ids.every((id) => folds[id]));

                ids.forEach((id) => (folds[id] = value));
                save();
                this.apply();
            },
        };
    });

    // Simpan berkas materi untuk dibaca offline. Pengambilan lewat service worker (public/sw.js) otomatis menyimpannya
    // di cache "aksaraloka-materials-v1"; tombol ini hanya memicu pengambilan penuh dan melaporkan statusnya.
    Alpine.data('offlineSave', ({ url }) => {
        const cacheName = 'aksaraloka-materials-v1';
        const key = () => new URL(url, location.href).origin + new URL(url, location.href).pathname;

        return {
            supported: 'caches' in window && 'serviceWorker' in navigator,
            saved: false,
            busy: false,

            async init() {
                if (!this.supported) {
                    return;
                }

                try {
                    this.saved = !!(await (await caches.open(cacheName)).match(key()));
                } catch {
                    this.supported = false;
                }
            },

            async save() {
                this.busy = true;

                try {
                    const response = await fetch(url, { credentials: 'same-origin' });

                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    await response.arrayBuffer();
                    // Bila service worker belum mengendalikan halaman ini, simpan langsung dari sini.
                    const cache = await caches.open(cacheName);

                    if (!(await cache.match(key()))) {
                        await cache.put(key(), (await fetch(url, { credentials: 'same-origin' })).clone());
                    }

                    this.saved = true;
                    this.$dispatch('toast', { message: this.$el.dataset.done ?? 'Tersimpan', type: 'success' });
                } catch {
                    this.$dispatch('toast', { message: this.$el.dataset.failed ?? 'Gagal menyimpan', type: 'error' });
                } finally {
                    this.busy = false;
                }
            },

            async remove() {
                await (await caches.open(cacheName)).delete(key());
                this.saved = false;
            },
        };
    });

    // Render rumus $...$ dan $$...$$ pada konten (soal, materi) dengan KaTeX.
    Alpine.data('mathContent', () => ({
        async init() {
            const [{ default: render }] = await Promise.all([
                import('katex/dist/contrib/auto-render'),
                import('katex/dist/katex.min.css'),
            ]);

            render(this.$el, {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$', right: '$', display: false },
                ],
                throwOnError: false,
            });
        },
    }));

    // Chart.js dengan warna dari token tema; digambar ulang saat tema berganti.
    Alpine.data('themedChart', ({ type = 'bar', data, options = {} }) => {
        let chart = null;
        let ChartCtor = null;
        let onTheme = null;

        return {
            async init() {
                const { Chart, registerables } = await import('chart.js');

                Chart.register(...registerables);
                ChartCtor = Chart;
                this.build();

                onTheme = () => this.build();
                window.addEventListener('theme-changed', onTheme);
            },

            token(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim();
            },

            build() {
                chart?.destroy();

                // Aksen merah mirip warna bahaya, jadi warna ke-4 memakai abu-abu agar seri grafik tetap mudah dibedakan.
                const palette = [this.token('primary'), this.token('success'), this.token('warning'), this.token('text-muted')];

                ChartCtor.defaults.color = this.token('text-muted');
                ChartCtor.defaults.borderColor = this.token('border');
                ChartCtor.defaults.font.family = getComputedStyle(document.body).fontFamily;

                chart = new ChartCtor(this.$refs.canvas, {
                    type,
                    data: {
                        ...data,
                        datasets: data.datasets.map((set, i) => ({
                            // `mono: true` = satu warna untuk seluruh batang (seri tunggal).
                            backgroundColor: type === 'line' || set.mono ? palette[i % 4] : palette,
                            borderColor: palette[i % 4],
                            ...set,
                        })),
                    },
                    options: { responsive: true, maintainAspectRatio: false, ...options },
                });
            },

            destroy() {
                window.removeEventListener('theme-changed', onTheme);
                chart?.destroy();
            },
        };
    });
}
