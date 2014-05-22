<?php namespace Flynsarmy\Menu\FormWidgets;

use Request;
use Backend\Classes\FormWidgetBase;
use Flynsarmy\Menu\Models\Menu;
use Flynsarmy\Menu\Models\MenuItem;
use Flynsarmy\Menu\Classes\MenuManager;
use Exception;

/**
 * Rich Editor
 * Renders a rich content editor field.
 *
 * @package october\backend
 * @author Alexey Bobkov, Samuel Georges
 */
class ItemList extends FormWidgetBase
{
	/**
	 * {@inheritDoc}
	 */
	public $defaultAlias = 'itemlist';

	/**
	 * {@inheritDoc}
	 */
	public function render()
	{
		$this->prepareVars();
		return $this->makePartial('itemlist');
	}

	/**
	 * Prepares the list data
	 */
	public function prepareVars()
	{
		$this->vars['stretch'] = $this->formField->stretch;
		$this->vars['size'] = $this->formField->size;
		$this->vars['name'] = $this->formField->getName();
		$this->vars['value'] = $this->model->{$this->columnName};
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadAssets()
	{
		// $this->addJs('js/itemlist.js');
	}

	public function onLoadTypeSelection()
	{
		$this->vars['item_types'] = MenuManager::instance()->listItemTypes();
		return $this->makePartial('type_selection');
	}

	public function onLoadCreateItem()
	{
		try {
			$type = post('type_id');
			if (!$type = MenuManager::instance()->resolveItemType($type))
				throw new Exception('Invalid item type: '. post('type_id'));

			$this->vars['type'] = $type;
			$itemTypes = MenuManager::instance()->listItemTypes();
			$this->vars['itemType'] = $itemTypes[$type];

			/*
			 * Create a form widget to render the form
			 */
			$config = $this->makeConfig('@/plugins/flynsarmy/menu/models/menuitem/fields.yaml');
			$config->model = new MenuItem;
			$config->context = 'create';
			$form = $this->makeWidget('Backend\Widgets\Form', $config);

			$form->bindEvent('form.extendFields', function($widget) use ($type){
				$itemTypeObj = new $type;
				$itemTypeObj->formExtendFields($widget);
			});

			$this->vars['form'] = $form;

		}
		catch (Exception $ex) {
			$this->vars['fatalError'] = $ex->getMessage();
		}

		return $this->makePartial('create_item');
	}

	public function onCreateItem()
	{
		$menu = $this->controller->widget->form->model->exists() ?
			$this->controller->widget->form->model : new Menu;

		$item = new MenuItem;
		$item->fill(Request::input());
		$item->save();

		$menu->items()->add($item, Request::input('_session_key'));

		// \Log::info(print_r($_POST, true));
		$this->prepareVars();
		return [
			'#reorderRecords' => $this->makePartial('item_records', ['records' => $this->vars['value']])
		];
	}

	public function onMove()
	{
		$sourceNode = MenuItem::find(post('sourceNode'));
		$targetNode = post('targetNode') ? MenuItem::find(post('targetNode')) : null;

		if ($sourceNode == $targetNode)
			return;

		switch (post('position')) {
			case 'before': $sourceNode->moveBefore($targetNode); break;
			case 'after': $sourceNode->moveAfter($targetNode); break;
			case 'child': $sourceNode->makeChildOf($targetNode); break;
			default: $sourceNode->makeRoot(); break;
		}
	}
}