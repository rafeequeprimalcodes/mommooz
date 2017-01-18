<?php

namespace App\FrontBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\FrontBundle\Entity\Region;
use App\FrontBundle\Form\RegionType;
use App\FrontBundle\Helper\FormHelper;
use Symfony\Component\HttpFoundation\JsonResponse;

class RegionController extends Controller
{
    /**
     * @Route("/results", name="region_results")
     */
    public function indexResultsAction()
    {
        $datatable = $this->get('app.front.datatable.region');
        $datatable->buildDatatable();
        $query = $this->get('sg_datatables.query')->getQueryFrom($datatable);
        return $query->getResponse();
    }
    
    /**
     * Displays an existing region entity.
     *
     * @Route("/show", name="region_show", options={"expose"=true})
     * @Method({"GET"})
     */
    public function showAction(Request $request)
    {
        // ...
    }
    
    /**
     * Displays a form to add an existing region entity.
     *
     * @Route("/new", name="region_new", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $dm = $this->getDoctrine()->getManager();
        $form = $this->createForm(new RegionType(), new Region());
        
        $code = FormHelper::FORM;
        if($request->isMethod('POST')){
            $form->handleRequest($request);
            if($form->isValid()){
                $region = $form->getData();
                $region->setStatus(true);
                $this->get('session')->getFlashBag()->add('success', 'region.msg.created');
                if($region->getDefault()){
                    $defaultRegion = $dm->getRepository('AppFrontBundle:Region')->findOneByDefault(true);
                    if($defaultRegion){
                        $defaultRegion->setDefault(false);
                        $dm->persist($defaultRegion);
                        $dm->flush();
                    }
                }
                $dm->persist($region);
                $dm->flush();
                $code = FormHelper::REFRESH;
            } else {
                $code = FormHelper::REFRESH_FORM;
            }
        }
        
        $body = $this->renderView('AppFrontBundle:Region:form.html.twig',
            array('form' => $form->createView())
        );
        
        return new Response(json_encode(array('code' => $code, 'data' => $body)));
    }
    
    /**
     * Displays a form to edit an existing region entity.
     *
     * @Route("/{id}/edit", name="region_edit", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Region $region)
    {
        $dm = $this->getDoctrine()->getManager();
        $form = $this->createForm(new RegionType(), $region);
        
        $code = FormHelper::FORM;
        if($request->isMethod('POST')){
            $form->handleRequest($request);
            if($form->isValid()){
                $region = $form->getData();
                $this->get('session')->getFlashBag()->add('success', 'region.msg.created');
                if($region->getDefault()){
                    $defaultRegion = $dm->getRepository('AppFrontBundle:Region')->findOneByDefault(true);
                    if($defaultRegion){
                        $defaultRegion->setDefault(false);
                        $dm->persist($defaultRegion);
                        $dm->flush();
                    }
                }
                $dm->persist($region);
                $dm->flush();
                $code = FormHelper::REFRESH;
            } else {
                $code = FormHelper::REFRESH_FORM;
            }
        }
        
        $body = $this->renderView('AppFrontBundle:Region:form.html.twig',
            array('form' => $form->createView())
        );
        
        return new Response(json_encode(array('code' => $code, 'data' => $body)));
    }
    
    /**
     * Displays a form to delete an existing region entity.
     *
     * @Route("/{id}/delete", name="region_delete", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function deleteAction(Request $request, Region $region)
    {
        $dm = $this->getDoctrine()->getManager();
        $dm->remove($region);
        $dm->flush();
        
        $this->get('session')->getFlashBag()->add('success', 'region.msg.removed');
        return new Response(json_encode(array('code' => FormHelper::REFRESH)));
    }
    
    /**
     * List Region details with location.
     *
     * @Route("/{id}/details", name="region_detail", options={"expose"=true})
     * @Method({"GET", "POST"})
     */
    public function detailAction(Request $request, Region $region)
    {
        $dm = $this->getDoctrine()->getManager();        
        $code = FormHelper::FORM;
        
        $locationDatatable = $this->get('app.front.datatable.location');
        $locationDatatable->buildDatatable(array('region' => $region));
        return $this->render('AppFrontBundle:Region:detail.html.twig',
            array('locationDatatable' => $locationDatatable)
        );
    }
    
    /**
     * @Route("/search", name="region_search")
     */
    public function searchAction(Request $request){
        $q = $request->query->get('q');
        $regions = $this->getDoctrine()->getManager()->getRepository("AppFrontBundle:Region")->createQueryBuilder('r')
        ->where('r.regionName LIKE :q')
        ->setParameter('q', '%'.$q.'%')
        ->getQuery()
        ->getResult();
        
        $result = array();
        foreach($regions as $region){
            $result[] = array('id' => $region->getId(), 'name' => $region->getRegionName().' ('.$region->getDistrict()->getName().')');
        }
        
        return new JsonResponse($result);
    }
}
