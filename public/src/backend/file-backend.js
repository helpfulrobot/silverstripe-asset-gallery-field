import $ from 'jquery';
import Events from 'events';

export default class FileBackend extends Events {
	static create(...parameters) {
		return new FileBackend(...parameters);
	}

	constructor(search_url, update_url, delete_url, limit, $folder) {
		super();

		this.search_url = search_url;
		this.update_url = update_url;
		this.delete_url = delete_url;
		this.limit = limit;
		this.$folder = $folder;

		this.page = 1;
	}

	search() {
		this.page = 1;

		this.request('GET', this.search_url).then((json) => {
			this.emit('onSearchData', json);
		});
	}

	more() {
		this.page++;

		this.request('GET', this.search_url).then((json) => {
			this.emit('onMoreData', json);
		});
	}

	navigate(folder) {
		this.page = 1;
		this.folder = folder;
		this.persistFolderFilter(folder);

		this.request('GET', this.search_url).then((json) => {
			this.emit('onNavigateData', json);
		});
	}

	persistFolderFilter(folder) {
		this.$folder.val(folder);
	}

	delete(id) {
		this.request('GET', this.delete_url, {
			'id': id
		}).then(() => {
			this.emit('onDeleteData', id);
		});
	}

	filter(folder) {
		this.folder = folder;
		this.persistFolderFilter(folder);
		this.search();
	}

	save(id, values) {
		values['id'] = id;

		this.request('POST', this.update_url, values).then(() => {
			this.emit('onSaveData', id, values);
		});
	}

	request(method, url, data = {}) {
		let defaults = {
			'limit': this.limit,
			'page': this.page,
			'folder': this.folder
		};

		this.showLoadingIndicator();

		return $.ajax({
			'url': url,
			'method': method,
			'dataType': 'json',
			'data': $.extend(defaults, data)
		}).always(() => {
			this.hideLoadingIndicator();
		});
	}

	showLoadingIndicator() {
		$('.cms-content, .ui-dialog').addClass('loading');
		$('.ui-dialog-content').css('opacity', '.1');
	}

	hideLoadingIndicator() {
		$('.cms-content, .ui-dialog').removeClass('loading');
		$('.ui-dialog-content').css('opacity', '1');
	}
}
