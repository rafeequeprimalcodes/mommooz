<?php

namespace App\FrontBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\FrontBundle\Entity\Product;
use App\FrontBundle\Form\ProductType;
use App\FrontBundle\Helper\FormHelper;

class ProductController extends Controller
{
    /**
     * @Route("/results", name="product_results", options={"expose"=true})
     */
    public function indexResultsAction()
    {
        $datatable = $this->get('app.front.datatable.product');
        $datatable->buildDatatable();
        $query = $this->get('sg_datatables.query')->getQueryFrom($datatable);
        return $query->getResponse();
    }
    
    /**
     * Displays a form to add an existing product entity.
     *
     * @Route("/new", name="product_new", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $dm = $this->getDoctrine()->getManager();
        $form = $this->createForm(new ProductType(), new Product());
        
        $code = FormHelper::FORM;
        if($request->isMethod('POST')){
            $form->handleRequest($request);
            if($form->isValid()){
                $product = $form->getData();
                $product->setStatus(true);
                $dm->persist($product);
                $dm->flush();
                $this->get('session')->getFlashBag()->add('success', 'product.msg.created');
                $code = FormHelper::REFRESH;
            } else {
                $code = FormHelper::REFRESH_FORM;
            }
        }
        
        $body = $this->renderView('AppFrontBundle:Product:form.html.twig',
            array('form' => $form->createView())
        );

        return new Response(json_encode(array('code' => $code, 'data' => $body)));
    }
    
    /**
     * Displays a form to edit an existing product entity.
     *
     * @Route("/{id}/edit", name="product_edit", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Product $product)
    {
        $dm = $this->getDoctrine()->getManager();
        $form = $this->createForm(new ProductType(), $product);
        
        $code = FormHelper::FORM;
        if($request->isMethod('POST')){
            $form->handleRequest($request);
            if($form->isValid()){
                $product = $form->getData();
                $dm->persist($product);
                $dm->flush();
                $this->get('session')->getFlashBag()->add('success', 'product.msg.created');
                $code = FormHelper::REFRESH;
            } else {
                $code = FormHelper::REFRESH_FORM;
            }
        }
        
        $body = $this->renderView('AppFrontBundle:Product:form.html.twig',
            array('form' => $form->createView())
        );
        
        return new Response(json_encode(array('code' => $code, 'data' => $body)));
    }
    
    /**
     * Displays a form to delete an existing product entity.
     *
     * @Route("/{id}/delete", name="product_delete", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function deleteAction(Request $request, Product $product)
    {
        $dm = $this->getDoctrine()->getManager();
        $dm->remove($product);
        $dm->flush();
        
        $this->get('session')->getFlashBag()->add('success', 'product.msg.removed');
        return new Response(json_encode(array('code' => FormHelper::REFRESH)));
    }
    
    /**
     * List Region details with location.
     *
     * @Route("/items/{id}", name="product_items", defaults={"id":0}, options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function itemsAction(Request $request, Product $product = null)
    {
        $itemDatatable = $this->get('app.front.datatable.item');
        $itemDatatable->buildDatatable(array('product' => $product));
        return $this->render('AppFrontBundle:Admin:item.html.twig', array(
            'itemDatatable' => $itemDatatable,
        ));
    }
}
