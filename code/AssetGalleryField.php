<?php

namespace SilverStripe\Forms;

use Controller;
use File;
use Folder;
use FormField;
use Member;
use Requirements;
use SS_HTTPRequest;
use SS_HTTPResponse;
use SS_List;

class AssetGalleryField extends FormField {

	/**
	 * @var SS_List
	 */
	protected $source = null;

	/**
	 * Create a new AssetGalleryField
	 *
	 * @param string $name The internal field name, passed to forms.
	 * @param null|string $title The human-readable field label.
	 * @param SS_List $source Data source
	 */
	public function __construct($name, $title = null, SS_List $source = null) {
		parent::__construct($name, $title);

		// Set source, if given
		if($source) {
			$this->setSource($source);
		}

		// Set default folder
		$this->setCurrentPath($this->config()->defaultPath);
	}

	/**
	 * Get datasource for this list.
	 *
	 * @return SS_List
	 */
	public function getSource() {
		return $this->source ?: File::get();
	}

	/**
	 * Assign a new datasource
	 *
	 * @param SS_List $source
	 */
	public function setSource(SS_List $source) {
		$this->source = $source;
	}

	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'search',
		'update',
		'delete',
	);

	/**
	 * @config
	 *
	 * @var string
	 */
	private static $defaultPath = 'Uploads';

	/**
	 * ID of current folder
	 *
	 * @var int
	 */
	protected $currentFolderID;

	/**
	 * @var int
	 */
	protected $limit = 10;

	/**
	 * @return $this
	 */
	public function performReadonlyTransformation() {
		return $this;
	}

	/**
	 * @return string
	 */
	public function Type() {
		return 'asset-gallery';
	}

	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return SS_HTTPResponse
	 */
	public function search(SS_HTTPRequest $request) {
		$filters = array();

		$folder = null;
		if ($folderID = $request->getVar('folder')) {
			$folder = Folder::get()->byID($folderID);
		}
		if(!$folder) {
			$folderID = 0;
		}
		$filters['folder'] = $folderID;
		$filters['page'] = 1;
		$filters['limit'] = 10;

		if ($page = $request->getVar('page')) {
			$filters['page'] = $page;
		}

		if ($limit = $request->getVar('limit')) {
			$filters['limit'] = $limit;
		}

		$data = $this->getData($filters);

		$response = new SS_HTTPResponse();
		$response->addHeader('Content-Type', 'application/json');
		$response->setBody(json_encode(array(
			'files' => $data['items'],
			'count' => $data['count'],
			'folderid' => $folderID,
			'parentid' => $folder ? $folder->ParentID : 0
		)));

		return $response;
	}

	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return SS_HTTPResponse
	 */
	public function update(SS_HTTPRequest $request) {
		$id = $request->getVar('id');
		$file = File::get()->filter('id', (int) $id)->first();

		$code = 500;

		$body = array(
			'status' => 'error'
		);

		if ($file) {
			$title = $request->getVar('title');
			$basename = $request->getVar('basename');

			if (!empty($title)) {
				$file->Title = $title;
			}

			if (!empty($basename)) {
				$file->Name = $basename;
			}

			$file->write();

			$code = 200;

			$body = array(
				'status' => 'ok'
			);
		}

		$response = new SS_HTTPResponse(json_encode($body), $code);
		$response->addHeader('Content-Type', 'application/json');

		return $response;
	}

	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return SS_HTTPResponse
	 */
	public function delete(SS_HTTPRequest $request) {
		$file = File::get()->filter("id", (int) $request->getVar("id"))->first();

		$response = new SS_HTTPResponse();
		$response->addHeader('Content-Type', 'application/json');

		if ($file) {
			$file->delete();

			$response->setBody(json_encode(array(
				'status' => 'file was deleted',
			)));
		} else {
			$response->setStatusCode(500);

			$response->setBody(json_encode(array(
				'status' => 'could not find the file',
			)));
		}

		return $response;
	}

	/**
	 * @param array $filters
	 *
	 * @return array
	 */
	protected function getData($filters = array()) {
		// Re-apply folder filter to search
		$files = $this->getSource();

		if (isset($filters['folder'])) {
			$files = $files->filter('ParentID', $filters['folder']);
		}
		
		$files = $files->sort(
			'(CASE WHEN "File"."ClassName" = \'Folder\' THEN 0 ELSE 1 END), "Name"'
		);

		// Total count before applying limit
		$count = $files->count();

		// Page this list
		if (isset($filters['page']) && isset($filters['limit'])) {
			$page = $filters['page'];
			$limit = $filters['limit'];
			$offset = ($page - 1) * $limit;
			$files = $files->limit($limit, $offset);
		}

		$items = array();
		foreach($files as $file) {
			$items[] = $this->getObjectFromData($file);
		}

		return array(
			"items" => $items,
			"count" => $count,
		);
	}

	/**
	 * @inheritdoc
	 *
	 * @param array $properties
	 *
	 * @return string
	 */
	public function Field($properties = array()) {
		$name = $this->getName();

		Requirements::css(ASSET_GALLERY_FIELD_DIR . "/public/dist/main.css");
		Requirements::add_i18n_javascript(ASSET_GALLERY_FIELD_DIR . "/javascript/lang");
		Requirements::javascript(ASSET_GALLERY_FIELD_DIR . "/public/dist/bundle.js");

		$searchURL = $this->getSearchURL();
		$updateURL = $this->getUpdateURL();
		$deleteURL = $this->getDeleteURL();
		$folder = $this->getCurrentFolder();
		$folderID = $this->getCurrentFolderID();
		$parentID = 0;
		if($folder) {
			$parentID = $folder->ParentID;
		}
		$limit = $this->getLimit();

		return "<div
			class='asset-gallery'
			data-asset-gallery-name='{$name}'
			data-asset-gallery-limit='{$limit}'
			data-asset-gallery-search-url='{$searchURL}'
			data-asset-gallery-update-url='{$updateURL}'
			data-asset-gallery-delete-url='{$deleteURL}'
			data-asset-gallery-folderid='{$folderID}'
			data-asset-gallery-parentid='{$parentID}'
			></div>";
	}

	/**
	 * @return string
	 */
	protected function getSearchURL() {
		return Controller::join_links($this->Link(), 'search');
	}

	/**
	 * @return string
	 */
	protected function getUpdateURL() {
		return Controller::join_links($this->Link(), 'update');
	}

	/**
	 * @return string
	 */
	protected function getDeleteURL() {
		return Controller::join_links($this->Link(), 'delete');
	}

	/**
	 * Allows the current folder to be modified via a filename reference
	 *
	 * @param string $currentPath
	 * @return $this
	 */
	public function setCurrentPath($currentPath) {
		$folder = Folder::find_or_make($currentPath);
		$folderID = $folder ? $folder->ID : 0;
		return $this->setCurrentFolderID($folderID);
	}

	/**
	 * Set the ID of the folder being viewed. May be 0 for root.
	 *
	 * @param int $id
	 * @return $this
	 */
	public function setCurrentFolderID($id) {
		$this->currentFolderID = $id;
		return $this;
	}

	/**
	 * Gets the ID of the folder being viewed. May be 0 for root.
	 *
	 * @return int
	 */
	public function getCurrentFolderID() {
		return $this->currentFolderID;
	}

	/**
	 * If viewing a folder, return the object
	 *
	 * @return Folder|null
	 */
	public function getCurrentFolder() {
		$folderID = $this->getCurrentFolderID();
		if($folderID) {
			return Folder::get()->byID($folderID);
		}
	}

	/**
	 * @param File $file
	 *
	 * @return array
	 */
	protected function getObjectFromData(File $file) {
		$thumbnail = $file->Thumbnail(200, 150);
		$object = array(
			'id' => $file->ID,
			'parentid' => $file->ParentID,
			'created' => $file->Created,
			'lastUpdated' => $file->LastEdited,
			'owner' => null,
			'parent' => null,
			'attributes' => array(
				'dimensions' => array(),
			),
			'title' => $file->Title,
			'type' => $file->is_a('Folder') ? 'folder' : $file->FileType,
			'category' => $file->is_a('Folder') ? 'folder' : $file->appCategory(),
			'basename' => $file->Name,
			'filename' => $file->Filename,
			'extension' => $file->Extension,
			'size' => $file->Size,
			'thumbnail' => $thumbnail ? $thumbnail->getURL() : null,
			'url' => $file->AbsoluteURL,
		);

		/** @var Member $owner */
		$owner = $file->Owner();

		if($owner) {
			$object['owner'] = array(
				'id' => $owner->ID,
				'title' => trim($owner->FirstName . ' ' . $owner->Surname),
			);
		}

		/** @var Folder $parent */
		$parent = $file->Parent();

		if($parent) {
			$object['parent'] = array(
				'id' => $parent->ID,
				'title' => $parent->Title,
				'filename' => $parent->Filename,
			);
		}

		/** @var File $file */
		if($file->hasMethod('getWidth') && $file->hasMethod('getHeight')) {
			$object['attributes']['dimensions']['width'] = $file->Width;
			$object['attributes']['dimensions']['height'] = $file->Height;
		}

		return $object;
	}

	/**
	 * @param int $limit
	 *
	 * @return $this
	 */
	public function setLimit($limit) {
		$this->limit = $limit;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return $this->limit;
	}
}
