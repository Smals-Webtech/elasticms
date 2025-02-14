import ajaxModal from "./../helper/ajaxModal";
import {ajaxJsonGet, ajaxJsonPost} from "../helper/ajax";
import ProgressBar from "../helper/progressBar";
import FileUploader from "@elasticms/file-uploader";

export default class MediaLibrary {
    #el;
    #hash;
    #activePath = '/';
    #options = {};
    #elements = {};
    #loadedFiles = 0;

    constructor (el, options) {
        this.#options = options;
        this.#hash = el.dataset.hash;

        this.#el = el;
        this.#elements = {
            btnHome: el.querySelector('button.btn-home'),
            btnAddFolder: el.querySelector('button.btn-add-folder'),
            inputUpload:  el.querySelector('input.file-uploader-input'),
            files: el.querySelector('div.media-lib-files'),
            loadMoreFiles: el.querySelector('div.media-lib-files > div.media-lib-load-more'),
            listFiles: el.querySelector("ul.media-lib-list-files"),
            listFolders: el.querySelector("ul.media-lib-list-folders"),
            listUploads: el.querySelector('ul.media-lib-list-uploads'),
            listBreadcrumb: el.querySelector('ul.media-lib-list-breadcrumb')
        };
        if (this.#elements.inputUpload) this.#elements.uploadLabel = el.querySelector(`label[for="${this.#elements.inputUpload.id}"]`);

        this._init();
    }

    _init() {
        this._addEventListeners();
        this._disableButtons();
        Promise
            .allSettled([this._getFolders(), this._getFiles(this.#activePath, 0)])
            .then(() => this._enableButtons());
    }

    _disableButtons() {
        this.#el.querySelectorAll('button').forEach(button => button.disabled = true);
        if (this.#elements.uploadLabel) this.#elements.uploadLabel.setAttribute('disabled', 'disabled');
    }
    _enableButtons() {
        this.#el.querySelectorAll('button').forEach(button => button.disabled = false);
        if (this.#elements.uploadLabel) this.#elements.uploadLabel.removeAttribute('disabled');
    }

    _addEventListeners() {
        if (this.#elements.btnAddFolder) this.#elements.btnAddFolder.onclick = () => this._addFolder();
        if (this.#elements.btnHome) this.#elements.btnHome.onclick = () => this._loadFolder();
        if (this.#elements.inputUpload) this.#elements.inputUpload.onchange = (event) => { this._addFiles(Array.from(event.target.files)); };
        this.#el.onclick = (event) => {
            if (event.target.classList.contains('media-lib-link-folder')) {
                this._loadFolder(event.target.dataset.path, event.target);
            }
        }

        this._initDropArea(this.#elements.files);
        this._initInfiniteScrollFiles(this.#elements.files, this.#elements.loadMoreFiles);
    }

    _addFiles(files) {
        this._disableButtons();
        let path = this.#activePath;

        Promise
            .allSettled(files.map((file) => this._addFile(file, path)))
            .then(() => {
                this.#elements.inputUpload.value = "";
                this._loadFolder(path);
            });
    }
    _addFile(file, path) {
        return new Promise((resolve, reject) => {
            let id = 'upload-' + Date.now();
            let progressBar = new ProgressBar('progress-' + id, {
                'label': file.name
            });

            let fileHash = null;
            let mediaLib = this;
            let liUpload = document.createElement('li');
            liUpload.append(progressBar.element());
            this.#elements.listUploads.appendChild(liUpload);

            new FileUploader({
                file: file,
                algo: this.#options.hashAlgo,
                initUrl: this.#options.urlInitUpload,
                onHashAvailable: function (hash) {
                    progressBar.status('Hash available');
                    progressBar.progress(0);
                    fileHash = hash;
                },
                onProgress: function (status, progress, remaining) {
                    if (status === 'Computing hash') {
                        progressBar.status('Calculating ...');
                        progressBar.progress(remaining);
                    }
                    if (status === 'Uploading') {
                        progressBar.status('Uploading: ' + remaining);
                        progressBar.progress(Math.round(progress * 100));
                    }
                },
                onUploaded: function () {
                    progressBar.status('Uploaded');
                    progressBar.progress(100);
                    progressBar.style('success');

                    ajaxJsonPost(mediaLib._makeUrl('add-file/' + fileHash, { 'path': path }), JSON.stringify({
                        'file': { 'filename': file.name, 'filesize': file.size, 'mimetype': file.type}
                    }), (json, request) => {
                        if (request.status === 201) {
                            resolve();
                            mediaLib.#elements.listUploads.removeChild(liUpload);
                        } else {
                            reject();
                            progressBar.status('Error: ' + message);
                            progressBar.progress(100);
                            progressBar.style('danger');
                        }
                    });
                },
                onError: function (message) {
                    progressBar.status('Error: ' + message);
                    progressBar.progress(100);
                    progressBar.style('danger');
                }
            });

        });
    }
    _addFolder() {
        ajaxModal.load({ url: this._makeUrl('add-folder'), size: 'sm'}, (json) => {
            if (json.hasOwnProperty('success') && json.success === true) {
                this._disableButtons();
                this._getFolders(json.path).then(() => this._enableButtons());
            }
        });
    }

    _loadFolder(path, clickedButton) {
        this._disableButtons();

        this.#elements.listFolders.querySelectorAll('button')
            .forEach((li) => li.classList.remove('active'));

        let button = document.querySelector(`button[data-path="${path}"]`);
        if (button) { button.classList.add('active'); }

        if (clickedButton) {
            let parentLi = clickedButton.parentNode;
            if (parentLi && parentLi.classList.contains('media-lib-folder-children')) {
                parentLi.classList.toggle('open');
            }
        }

        this.#activePath = path;
        this._getFiles(path, 0).then(() => this._enableButtons());
    }
    _openPath(path) {
        let currentPath = '';
        path.split('/').filter(f => f !== '').forEach((folderName) => {
            currentPath += `/${folderName}`;

            let parentButton = document.querySelector(`button[data-path="${currentPath}"]`);
            let parentLi = parentButton ? parentButton.parentNode : null;

            if (parentLi && parentLi.classList.contains('media-lib-folder-children')) {
                parentLi.classList.add('open');
            }
        });

        if ('' !== currentPath) {
            let button = document.querySelector(`button[data-path="${currentPath}"]`);
            if (button) button.classList.add('active');
        }
    }

    _getFiles(path, from) {
        if (0 === from) {
            this.#loadedFiles = 0;
            this.#elements.listFiles.innerHTML = '';
            this._appendBreadcrumbItems(path, this.#elements.listBreadcrumb);
        }

        return fetch(this._makeUrl('files', { from: from }), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json'}
        }).then((response) => {
            return response.ok ? response.json().then((json) => this._appendFiles(json)) : Promise.reject(response);
        });
    }
    _getFolders(openPath) {
        return new Promise((resolve) => {
            this.#elements.listFolders.innerHTML = '';
            ajaxJsonGet([this.#options.urlMediaLib, this.#hash, 'folders'].join('/'), (folders) => {
                this._appendFolderItems(folders, this.#elements.listFolders);
                if (openPath) { this._openPath(openPath); }
                resolve();
            });
        });
    }

    _appendBreadcrumbItems(path, list) {
        if (null === list) return;

        list.style.display = 'flex';
        list.innerHTML = '';
        path = ''.concat('/home', path || '');
        let currentPath = '';

        path.split('/').filter(f => f !== '').forEach((folderName) => {
            if (folderName !== 'home') {
                currentPath = currentPath.concat('/', folderName);
            }

            let item = document.createElement('li');
            item.appendChild(this._makeFolderButton(folderName, currentPath));

            list.appendChild(item);
        });
    }
    _appendFiles(json) {
        if (json.hasOwnProperty('rowHeader')) {
            this.#elements.listFiles.innerHTML += json.rowHeader;
        }

        if (json.hasOwnProperty('rows')) {
            json.rows.forEach((row) => { this.#elements.listFiles.innerHTML += row; });
        }

        if (json.hasOwnProperty('totalRows')) {
            this.#loadedFiles += json.totalRows;
        }

        if (json.hasOwnProperty('remaining') && json.remaining) {
            this.#elements.loadMoreFiles.classList.add('show-load-more');
        } else {
            this.#elements.loadMoreFiles.classList.remove('show-load-more');
        }
    }
    _appendFolderItems(folders, list) {
        folders.forEach((folder) => {
            let liFolder = document.createElement("li");
            liFolder.appendChild(this._makeFolderButton(folder['name'], folder['path']));

            if (folder.hasOwnProperty('children')) {
                let ulChildren = document.createElement('ul');
                this._appendFolderItems(folder.children, ulChildren);
                liFolder.appendChild(ulChildren);
                liFolder.classList.add('media-lib-folder-children');
            }

            list.appendChild(liFolder);
        });
    }

    _makeFolderButton(name, path) {
        let button = document.createElement("button");
        button.disabled = true;
        button.textContent = name;
        button.dataset.path = path;
        button.classList.add('media-lib-link-folder');

        return button;
    }
    _makeUrl(action, params) {
        let url = [this.#options.urlMediaLib, this.#hash, action].join('/');
        let searchParams = params || {};

        if (!searchParams.hasOwnProperty('path') && this.#activePath) {
            searchParams.path = this.#activePath;
        }

        url += '?' + new URLSearchParams(searchParams).toString();

        return url;
    }
    _initDropArea(dropArea)  {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('media-lib-drop-area'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('media-lib-drop-area'), false);
        });

        dropArea.addEventListener('drop', () => {
            const files = event.target.files || event.dataTransfer.files;
            this._addFiles(Array.from(files));
        }, false);
    }

    _initInfiniteScrollFiles(scrollArea, divLoadMore) {
        const options = {
            root: scrollArea,
            rootMargin: "0px",
            threshold: 0.5
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    this._disableButtons();
                    this._getFiles(this.#activePath, this.#loadedFiles).then(() => this._enableButtons());
                }
            });
        }, options);

        observer.observe(divLoadMore);
    }
}