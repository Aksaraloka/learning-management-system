/**
 * Komponen Alpine yang membungkus library berat. Setiap library di-import
 * dinamis di dalam init() sehingga hanya diunduh di halaman yang memakainya.
 */
export function registerLazyComponents(Alpine) {
    Alpine.data('tomSelect', (options = {}) => ({
        instance: null,

        async init() {
            const [{ default: TomSelect }] = await Promise.all([
                import('tom-select'),
                import('../../css/vendor/tom-select.css'),
            ]);

            this.instance = new TomSelect(this.$refs.select, {
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
            this.instance?.destroy();
        },
    }));

    Alpine.data('datePicker', (options = {}) => ({
        instance: null,

        async init() {
            const [{ default: flatpickr }, { Indonesian }] = await Promise.all([
                import('flatpickr'),
                import('flatpickr/dist/l10n/id.js'),
                import('../../css/vendor/flatpickr.css'),
            ]);

            this.instance = flatpickr(this.$refs.input, {
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
            this.instance?.destroy();
        },
    }));

    Alpine.data('richEditor', (options = {}) => ({
        editor: null,
        active: {},

        async init() {
            const [{ Editor }, { default: StarterKit }] = await Promise.all([
                import('@tiptap/core'),
                import('@tiptap/starter-kit'),
                import('../../css/vendor/editor.css'),
            ]);

            const field = this.$refs.field;

            this.editor = new Editor({
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
                onUpdate: ({ editor }) => {
                    field.value = editor.isEmpty ? '' : editor.getHTML();
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    this.refresh();
                },
                onSelectionUpdate: () => this.refresh(),
            });

            this.refresh();
        },

        refresh() {
            const e = this.editor;

            this.active = {
                bold: e.isActive('bold'),
                italic: e.isActive('italic'),
                h2: e.isActive('heading', { level: 2 }),
                bullet: e.isActive('bulletList'),
                ordered: e.isActive('orderedList'),
            };
        },

        run(command) {
            const chain = this.editor.chain().focus();

            ({
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                h2: () => chain.toggleHeading({ level: 2 }),
                bullet: () => chain.toggleBulletList(),
                ordered: () => chain.toggleOrderedList(),
            })[command]().run();
        },

        destroy() {
            this.editor?.destroy();
        },
    }));

    Alpine.data('filePicker', (options = {}) => ({
        pond: null,

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

            const input = this.$refs.input;
            const livewireProp = options.livewireProp;

            this.pond = create(input, {
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
            this.pond?.destroy();
        },
    }));

    Alpine.data('pdfViewer', ({ url }) => ({
        page: 1,
        pages: 0,
        loading: true,
        failed: false,
        doc: null,

        async init() {
            try {
                const pdfjs = await import('pdfjs-dist');
                const worker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');

                pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

                this.doc = await pdfjs.getDocument(url).promise;
                this.pages = this.doc.numPages;

                await this.render();
            } catch {
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },

        async render() {
            const page = await this.doc.getPage(this.page);
            const canvas = this.$refs.canvas;
            const width = this.$refs.wrap.clientWidth;
            const base = page.getViewport({ scale: 1 });
            const scale = (width / base.width) * Math.min(window.devicePixelRatio || 1, 2);
            const viewport = page.getViewport({ scale });

            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = '100%';

            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        },

        go(delta) {
            const next = this.page + delta;

            if (next < 1 || next > this.pages) {
                return;
            }

            this.page = next;
            this.render();
        },
    }));

    Alpine.data('sortableList', (options = {}) => ({
        instance: null,

        async init() {
            const { default: Sortable } = await import('sortablejs');

            this.instance = Sortable.create(this.$el, {
                animation: 150,
                handle: '[data-handle]',
                ghostClass: 'opacity-40',
                onEnd: () => {
                    const ids = [...this.$el.children].map((el) => el.dataset.id);

                    this.$dispatch('sorted', { ids });
                    options.onSort?.(ids);
                },
            });
        },

        destroy() {
            this.instance?.destroy();
        },
    }));

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
    Alpine.data('themedChart', ({ type = 'bar', data, options = {} }) => ({
        chart: null,
        Chart: null,

        async init() {
            const { Chart, registerables } = await import('chart.js');

            Chart.register(...registerables);
            this.Chart = Chart;
            this.build();

            window.addEventListener('theme-changed', () => this.build());
        },

        token(name) {
            return getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim();
        },

        build() {
            this.chart?.destroy();

            const text = this.token('text-muted');
            const border = this.token('border');
            const palette = [this.token('primary'), this.token('success'), this.token('warning'), this.token('danger')];

            this.Chart.defaults.color = text;
            this.Chart.defaults.borderColor = border;
            this.Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

            this.chart = new this.Chart(this.$refs.canvas, {
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
            this.chart?.destroy();
        },
    }));
}
