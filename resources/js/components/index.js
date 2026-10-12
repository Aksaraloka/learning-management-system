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
                    onEnd: () => {
                        const ids = [...this.$el.children].map((el) => el.dataset.id);

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
                            backgroundColor: type === 'line' ? palette[i % 4] : palette,
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
