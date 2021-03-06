<?php

namespace App\FrontBundle\Datatables;

use Sg\DatatablesBundle\Datatable\View\AbstractDatatableView;
use Sg\DatatablesBundle\Datatable\View\Style;

/**
 * Class StockEntryDatatable
 *
 * @package App\FrontBundle\Datatables
 */
class StockEntryDatatable extends AbstractDatatableView
{
    public $requestStack;
    private $start = 0;
    private $sl = 1;
    
    protected function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLineFormatter()
    {
        $repo = $this->em->getRepository('AppFrontBundle:StockEntry');
        $this->start = $this->getRequest()->query->get('start', 0);
        
        $formatter = function($line) use ($repo){
            $entry = $repo->find($line['id']);
            $line['in_stock'] = $entry->getInStock();
            $line['commission'] = $line['actualPrice'] - $line['price'];
            $line['sl'] = $this->start + $this->sl++;
            
            return $line;
        };

        return $formatter;
    }

    
    /**
     * {@inheritdoc}
     */
    public function buildDatatable(array $options = array())
    {
        $id = isset($options['stock']) ? $options['stock']->getId() : 0;
        $published = isset($options['stock']) ? $options['stock']->getStatus() : 0;
        
        $this->topActions->set(array(
            'start_html' => '<div class="row"><div class="col-sm-12">',
            'end_html' => '<hr></div></div>',
            'actions' => array(
                array(
                    'route' => $this->router->generate('stockentry_new', array('id' => $id)),
                    'label' => $this->translator->trans('datatables.actions.new'),
                    'icon' => 'glyphicon glyphicon-plus',
                    'attributes' => array(
                        'rel' => 'tooltip',
                        'title' => $this->translator->trans('datatables.actions.new'),
                        'class' => 'btn btn-primary',
                        'role' => 'button',
                        'onclick' => 'return openModal(event);',
                        'modalTitle' => $this->translator->trans('stockentry.title.new'),
                    ),
                )
            )
        ));

        $this->features->set(array(
            'auto_width' => true,
            'defer_render' => false,
            'info' => true,
            'jquery_ui' => false,
            'length_change' => true,
            'ordering' => true,
            'paging' => true,
            'processing' => true,
            'scroll_x' => false,
            'scroll_y' => '',
            'searching' => true,
            'state_save' => true,
            'delay' => 0,
            'extensions' => array(),
            'highlight' => false,
            'highlight_color' => 'red'
        ));
        
        $this->ajax->set(array(
            'url' => $this->router->generate('stockentry_results', array('id' => $id)),
            'type' => 'GET',
            'pipeline' => 0
        ));

        $this->options->set(array(
            'display_start' => 0,
            'defer_loading' => -1,
            'dom' => 'lfrtip',
            'length_menu' => array(10, 25, 50, 100),
            'order_classes' => true,
            'order' => array(array(0, 'asc')),
            'order_multi' => true,
            'page_length' => 10,
            'paging_type' => Style::FULL_NUMBERS_PAGINATION,
            'renderer' => '',
            'scroll_collapse' => false,
            'search_delay' => 0,
            'state_duration' => 7200,
            'stripe_classes' => array(),
            'class' => Style::BOOTSTRAP_3_STYLE,
            'individual_filtering' => true,
            'individual_filtering_position' => 'head',
            'use_integration_options' => true,
            'force_dom' => false,
            'row_id' => 'id'
        ));

        $this->columnBuilder
            ->add('id', 'column', array(
                'title' => 'Id',
                'visible' => false,
            ))
            ->add('sl', 'virtual', array(
                'title' => 'Sl No',
            ))
            ->add('item.name', 'column', array(
                'title' => 'Item',
            ))
            ->add('item.brand.name', 'column', array(
                'title' => 'Brand',
            ))
            ->add('quantity', 'column', array(
                'title' => 'Quantity',
            ))
            ->add('in_stock', 'virtual', array(
                'title' => 'Available Stock',
            ))
            ->add('mrp', 'column', array(
                'title' => 'MRP',
            ))
            ->add('price', 'column', array(
                'title' => 'Price',
            ))
            ->add('commission', 'virtual', array(
                'title' => 'Commission',
            ))
            ->add('actualPrice', 'column', array(
                'title' => 'Display Price',
            ))
            ->add('status', 'boolean', array(
                'title' => 'Enabled',
                'true_icon' => 'glyphicon glyphicon-ok',
                'false_icon' => 'glyphicon glyphicon-remove',
                'true_label' => 'Yes',
                'false_label' => 'No'
            ))
            ->add(null, 'action', array(
                'title' => $this->translator->trans('datatables.actions.title'),
                'actions' => array(
                    array(
                        'route' => 'stockentry_edit',
                        'route_parameters' => array(
                            'id' => 'id'
                        ),
                        'label' => $this->translator->trans('datatables.actions.edit'),
                        'icon' => 'glyphicon glyphicon-edit',
                        'attributes' => array(
                            'rel' => 'tooltip',
                            'title' => $this->translator->trans('datatables.actions.edit'),
                            'class' => 'btn btn-primary btn-xs ',
                            'role' => 'button',
                            'onclick' => 'return openModal(event);',
                            'modalTitle' => $this->translator->trans('stockentry.title.edit'),
                            'style' => 'margin-right:5px;'
                        ),
                        'render_if' => function($row) use($published) {
                            return $published === false;
                        }
                    ),
                    array(
                        'route' => 'stockentry_cant_delete',
                        'route_parameters' => array(
                            'id' => 'id'
                        ),
                        'label' => $this->translator->trans('datatables.actions.delete'),
                        'icon' => 'glyphicon glyphicon-trash',
                        'attributes' => array(
                            'rel' => 'tooltip',
                            'title' => $this->translator->trans('datatables.actions.delete'),
                            'class' => 'btn btn-primary btn-xs ',
                            'role' => 'button',
                            'onclick' => 'return openAlert(event);',
                            'alertText' => 'Since there is purchase associated to this item.You cant delete this.',
                            'style' => 'margin-right:5px;'
                        ),
                        'render_if' => function($row) {
                            return ($row['quantity'] != $row['in_stock']);
                        }
                    ),
                    array(
                        'route' => 'stockentry_delete',
                        'route_parameters' => array(
                            'id' => 'id'
                        ),
                        'label' => $this->translator->trans('datatables.actions.delete'),
                        'icon' => 'glyphicon glyphicon-trash',
                        'attributes' => array(
                            'rel' => 'tooltip',
                            'title' => $this->translator->trans('datatables.actions.delete'),
                            'class' => 'btn btn-primary btn-xs ',
                            'role' => 'button',
                            'onclick' => 'return openConfirm(event);',
                            'cofirmText' => $this->translator->trans('stockentry.delete.confirm'),
                            'style' => 'margin-right:5px;'
                        ),
                        'render_if' => function($row) {
                            return ($row['quantity'] == $row['in_stock']);
                        }
                    ),
                    array(
                        'route' => 'stockentry_add',
                        'route_parameters' => array(
                            'id' => 'id'
                        ),
                        'label' => 'Add Stock',
                        'icon' => 'glyphicon glyphicon-plus-sign',
                        'attributes' => array(
                            'rel' => 'tooltip',
                            'title' => $this->translator->trans('stockentry.actions.add'),
                            'class' => 'btn btn-primary btn-xs',
                            'role' => 'button',
                            'onclick' => 'return openPrompt(event);',
                            'promptText' => $this->translator->trans('stockentry.add.prompt'),
                            'style' => 'margin-right:5px;'
                        ),
                        'render_if' => function($row) use($published) {
                            return $published === true;
                        }
                    ),
                    array(
                        'route' => 'stockentry_minus',
                        'route_parameters' => array(
                            'id' => 'id'
                        ),
                        'label' => 'Minus Stock',
                        'icon' => 'glyphicon glyphicon-minus-sign',
                        'attributes' => array(
                            'rel' => 'tooltip',
                            'title' => $this->translator->trans('stockentry.actions.minus'),
                            'class' => 'btn btn-primary btn-xs',
                            'role' => 'button',
                            'onclick' => 'return openPrompt(event);',
                            'promptText' => $this->translator->trans('stockentry.minus.prompt'),
                            'style' => 'margin-right:5px;'
                        ),
                        'render_if' => function($row) use ($published) {
                            return $published === true;
                        }
                    ),
                    array(
                        'route' => 'stockentry_manage',
                        'route_parameters' => array(
                            'id' => 'id'
                        ),
                        'label' => $this->translator->trans('stockentry.actions.manage'),
                        'icon' => 'glyphicon glyphicon-edit',
                        'attributes' => array(
                            'rel' => 'tooltip',
                            'title' => $this->translator->trans('stockentry.actions.manage'),
                            'class' => 'btn btn-primary btn-xs ',
                            'role' => 'button',
                            'onclick' => 'return openModal(event);',
                            'modalTitle' => $this->translator->trans('stockentry.title.manage'),
                            'style' => 'margin-right:5px;'
                        )
                    )
                )
            ))
        ;
        
        $this->callbacks->set(array(
            'row_callback' => array(
                'template' => 'AppFrontBundle:DataTable:row_callback.js.twig',
            )
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity()
    {
        return 'App\FrontBundle\Entity\StockEntry';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'stockentry_datatable';
    }
}
