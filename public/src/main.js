import $ from 'jquery';
import React from 'react';
import GalleryComponent from './component/gallery-component';
import FileBackend from './backend/file-backend';

function getVar(name) {
	var parts = window.location.href.split('?');

	if (parts.length > 1) {
		parts = parts[1].split('#');
	}

	let variables = parts[0].split('&');

	for (var i = 0; i < variables.length; i++) {
		let parts = variables[i].split('=');

		if (decodeURIComponent(parts[0]) === name) {
			return decodeURIComponent(parts[1]);
		}
	}

	return null;
}

$('.asset-gallery').entwine({
	'onadd': function () {
		let props = {
			'name': this[0].getAttribute('data-asset-gallery-name'),
			'folderid': this[0].getAttribute('data-asset-gallery-folderid'),
			'parentid': this[0].getAttribute('data-asset-gallery-parentid')
		};

		if (props.name === null) {
			return;
		}

		let $search = $('.cms-search-form');
		let $create = $();

		props.backend = FileBackend.create(
			this[0].getAttribute('data-asset-gallery-search-url'),
			this[0].getAttribute('data-asset-gallery-update-url'),
			this[0].getAttribute('data-asset-gallery-delete-url'),
			this[0].getAttribute('data-asset-gallery-limit'),
			$search.find('[type=hidden][name="q[Folder]"]')
		);

		React.render(
			<GalleryComponent {...props} />,
			this[0]
		);
	}
});
