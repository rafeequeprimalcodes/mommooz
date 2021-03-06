<?php

namespace App\WebBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\FrontBundle\Entity\StockEntry;
use App\FrontBundle\Entity\Item;
use App\FrontBundle\Entity\ItemView;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\FrontBundle\Entity\Cart;
use App\FrontBundle\Entity\CartItem;
use App\FrontBundle\Entity\WishList;
use App\FrontBundle\Entity\WishListItem;

class ItemController extends Controller
{
    /**
     * @Route("/{id}/page", name="item_page", options={"expose"=true})
     */
    public function pageAction(StockEntry $stockEntry)
    {
        if(!$stockEntry->isEnabled()){
            return new Response('Item not found or inactive...', 404);
        }

        $em = $this->getDoctrine()->getManager();

        $view = new ItemView();
        $view->setSessionId(session_id());
        $view->setUser($this->getUser());
        $view->setEntry($stockEntry);

        $em->persist($view);
        $em->flush();

        $query = $em->getRepository('AppFrontBundle:ItemView')->createQueryBuilder('iv');

        if($user = $this->getUser()){
            $query->where('iv.user = :user')
            ->setParameter('user', $user);
        } else {
            $query->where('iv.sessionId = :sessionId')
            ->setParameter('sessionId', session_id());
        }

        $query->andWhere('iv.entry <> :entry')
            ->setParameter('entry', $stockEntry);

        $query->groupBy('iv.entry')->orderBy('iv.viewedOn', 'DESC')->setMaxResults(3)
        ->getQuery();

        $recently_viewed = $query->getQuery()->getResult();

        if($user = $this->getUser()){
            $cart = $user->getCart();
        } else {
            $cart = $em->getRepository('AppFrontBundle:Cart')->findOneBySessionId(session_id());
        }

        if($user = $this->getUser()){
            $wishlist = $user->getWishlist();
        } else {
            $wishlist = $em->getRepository('AppFrontBundle:WishList')->findOneBySessionId(session_id());
        }

        $location = null;
//        if($loc_id = $this->get('session')->get('location')){
//            $location = $em->getRepository('AppFrontBundle:Location')->find($loc_id);
//        }

        return $this->render('AppWebBundle:Item:index.html.twig', array(
            'entry' => $stockEntry,
            'recently_viewed' => $recently_viewed,
            'cart' => $cart,
            'wishlist' => $wishlist,
            'location' => $location
        ));
    }

    /**
     * @Route("/{id}/variant", name="item_page_variant", options={"expose"=true})
     */
    public function variantAction(Request $request, Item $item)
    {
        $entries = $item->getLowCostEntries();
        $stockEntry = null;
        foreach($entries as $entry){
            if($entry->getItem()->getVariants()->count() > 0 && $entry->getVariant()->getId() == $request->query->get('variant')){
                $stockEntry = $entry;
            }
        }
        if($stockEntry){
            return $this->redirect($this->generateUrl('item_page', array('id' => $stockEntry->getId())));
        }

        return new Response('No Item Found...');
    }
    
    /**
     * @Route("/{id}/buy_now", name="item_buy_now", options={"expose"=true})
     */
    public function buyNowAction(StockEntry $stockEntry)
    {            
        $em = $this->getDoctrine()->getManager();
        $reward_money_config = $em->getRepository('AppFrontBundle:Config')->findOneByName('reward_money');
        $reward_money = 0;
        $total_rewards = 0;
        if($reward_money_config && $this->getUser()){
            $cart_price = $this->getUser()->getCart()->getPrice();
            $max_points_needed = round($cart_price*$reward_money_config->getValue(), 2);
            $rewards = $em->getRepository('AppFrontBundle:Reward')->findByConsumer($this->getUser());
            foreach($rewards as $reward){
                $total_rewards += $reward->getPoint();
                if($total_rewards > $max_points_needed){
                    $total_rewards = $max_points_needed;
                    break;
                }
            }
            
            if($reward_money_config->getValue() > 0){
                $reward_money = round($total_rewards/$reward_money_config->getValue(), 2);
            }
        }

        $delivery_charge = 0;
        $charges = $em->getRepository("AppFrontBundle:DeliveryCharge")->createQueryBuilder('c')
            ->where('c.priceFrom <= :p and c.priceTo >= :p')
            ->setParameter('p', $stockEntry->getActualPrice())
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if(count($charges) > 0){
            $delivery_charge = $charges[0]->getCharge();
        }
        
        return $this->render('AppWebBundle:Item:buyNow.html.twig', 
            array('reward_money' => $reward_money, 'total_rewards' => $total_rewards, 'entry' => $stockEntry, 'delivery_charge' => $delivery_charge)
        );
    }

    /**
     * @Route("/product/page/{id}", name="product_item_page", options={"expose"=true})
     */
    public function productitemAction(Item $item)
    {
        echo get_class($item); exit;
    }

    public function mostPurchasedAction(){
        $entries = array();
        $em = $this->getDoctrine()->getManager();
        $cat = $em->getRepository('AppFrontBundle:Category')->find($this->getParameter('traditional_cat'));
        if($cat){
            $qb = $em->createQueryBuilder();
            $qb->select('se')
                    ->from('AppFrontBundle:StockEntry', 'se')
                    ->join('se.item', 'i')
                    ->join('i.categories', 'c')
                    ->where('c.id = :cat_id')
                    ->andWhere('se.status = :status')
                    ->orderBy('se.id', 'DESC')
                    ->setParameter('status', true)
                    ->setParameter('cat_id', $cat->getId());
                    

            $entries = $qb->getQuery()->getResult();
        }

        return $this->render('AppWebBundle:Item:mostPurchased.html.twig', array(
            'entries' => $entries
        ));
    }

    public function newAction() {
        $entries = array();
        $em = $this->getDoctrine()->getManager();
        $cat = $em->getRepository('AppFrontBundle:Category')->find($this->getParameter('best_deals_cat'));
        if($cat){
            $qb = $em->createQueryBuilder();
            $qb->select('se')
                    ->from('AppFrontBundle:StockEntry', 'se')
                    ->join('se.item', 'i')
                    ->join('i.categories', 'c')
                    ->where('c.id = :cat_id')
                    ->andWhere('se.status = :status')
                    ->setParameter('status', true)
                    ->setParameter('cat_id', $cat->getId());
                    

            $entries = $qb->getQuery()->getResult();
        }
        
        return $this->render('AppWebBundle:Item:new.html.twig', array(
            'entries' => $entries
        ));
    }
    
    /**
     * @Route("/new/more", name="more_new_item")
     */
    public function morenewAction() {
        $entries = array();
        $em = $this->getDoctrine()->getManager();
        $cat = $em->getRepository('AppFrontBundle:Category')->find($this->getParameter('best_deals_cat'));
        if($cat){
            $qb = $em->createQueryBuilder();
            $qb->select('se')
                    ->from('AppFrontBundle:StockEntry', 'se')
                    ->join('se.item', 'i')
                    ->join('i.categories', 'c')
                    ->where('c.id = :cat_id')
                    ->andWhere('se.status = :status')
                    ->setParameter('status', true)
                    ->setParameter('cat_id', $cat->getId());
                    

            $entries = $qb->getQuery()->getResult();
        }
        
        $result = array();
        $app_web_user = $this->get('app.web.user');
        foreach($entries as $entry){
            if($app_web_user->isDeliverable($entry) && $entry->isEnabled()){
                $content = $this->renderView('AppWebBundle:Item:newItem.html.twig', array(
                    'entry' => $entry
                ));
                
                $result[] = array('id' => $entry->getId(), 'content' => $content);
            }
        }
        
        return new JsonResponse($result);
    }

    /**
     * @Route("/{id}/deliverable", name="item_deliverable", options={"expose"=true})
     */
    public function deliverableAction(Request $request, StockEntry $entry){
        $pin = $request->query->get('pin');
        $em = $this->getDoctrine()->getManager();
        $productDeliverablility = $entry->getItem()->getProduct()->getDeliverable();
        $status = false;
        $cost = 0;

        switch ($productDeliverablility){
            case 0:
                $regions = $entry->getStock()->getVendor()->getRegions();
                $status = $this->isDeliverable($regions, $pin);
                break;
            case 1:
                $status = true;
                break;
            case 2:
                $p_regions = $entry->getItem()->getProduct()->getRegions()->toArray();
                $v_regions = $entry->getStock()->getVendor()->getRegions()->toArray();
                $regions = array_merge($p_regions, $v_regions);
                $status = $this->isDeliverable($regions, $pin);
                break;
        }

        if($status){
            $location = $em->getRepository('AppFrontBundle:Location')->findOneByPinCode($pin);
            if($location){
                $pin_region = $location->getRegion();
                $v_regions = $entry->getStock()->getVendor()->getRegions();
                foreach($v_regions as $region){
                    if($region->getId() == $pin_region->getId()){
                        $cost = $location->getLocalServiceCharge();
                    } else {
                        $cost = $location->getRegionalServiceCharge();
                    }
                }   
            } else {
                $status = false;
            }
        }

        return new JsonResponse(array('status' => (int)$status, 'cost' => $cost));
    }

    public function isDeliverable($regions, $pin){
        $status = false;
        foreach($regions as $region){
            foreach ($region->getLocations() as $location){
                if($location->getPinCode() == $pin){
                    $status = true;
                    break;
                }
            }
        }

        return $status;
    }

    /**
     * @Route("/{id}/tocart", name="item_add_to_cart", options={"expose"=true})
     */
    public function tocartAction(Request $request, StockEntry $entry){
        $status = false;
        $quantity = 0;
        $in_cart = false;
        $em = $this->getDoctrine()->getManager();
        if($entry->getInStock() >= $request->query->get('qty')){
            $cart = null;
            if($user = $this->getUser()){
                $cart = $user->getCart();
                if(!$cart){
                    $cart = new Cart();
                    $cart->setUser($user);
                    $cart->setSessionId(session_id());
                    $em->persist($cart);
                    $em->flush();
                }


            } else {
                $cart = $em->getRepository('AppFrontBundle:Cart')->findOneBySessionId(session_id());
                if(!$cart){
                    $cart = new Cart();
                    $cart->setUser($this->getUser());
                    $cart->setSessionId(session_id());
                    $em->persist($cart);
                    $em->flush();
                }
            }

            if($cart){
                if($item = $cart->inCart($entry)){
                    $in_cart = true;
                    $item->setQuantity($item->getQuantity() + $request->query->get('qty'));
                } else {
                    $item = new CartItem();
                    $item->setCart($cart);
                    $item->setEntry($entry);
                    $item->setQuantity($request->query->get('qty'));
                    $item->setPrice($entry->getActualPrice());
                    $item->setStatus(false);
                }
                
                if($entry->getInStock() >= $item->getQuantity()){
                    $em->persist($item);
                    $em->flush();
                    $quantity = $item->getQuantity();
                    $status = true;
                }
            }
        }

        return new JsonResponse(array('status' => $status, 'quantity' => $quantity, 'in_cart' => $in_cart));
    }

    /**
     * @Route("/{id}/towinshlist", name="item_add_to_wishlist", options={"expose"=true})
     */
    public function towishlistAction(Request $request, StockEntry $entry){
        $status = false;
        $em = $this->getDoctrine()->getManager();
        $wishlist = null;
        if($user = $this->getUser()){
            $wishlist = $user->getWishlist();
            if(!$wishlist){
                $wishlist = new WishList();
                $wishlist->setUser($user);
                $wishlist->setSessionId(session_id());
                $em->persist($wishlist);
                $em->flush();
            }


        } else {
            $wishlist = $em->getRepository('AppFrontBundle:WishList')->findOneBySessionId(session_id());
            if(!$wishlist){
                $wishlist = new WishList();
                $wishlist->setUser($this->getUser());
                $wishlist->setSessionId(session_id());
                $em->persist($wishlist);
                $em->flush();
            }
        }

        if($wishlist){
            $item = new WishListItem();
            $item->setWishlist($wishlist);
            $item->setEntry($entry);
            $em->persist($item);
            $em->flush();

            $status = true;
        }

        return new JsonResponse(array('status' => $status));
    }
    
    /**
     * @Route("/{id}/thumb", name="item_thumb", options={"expose"=true})
     */
    public function thumbAction(StockEntry $entry) {
        if(Item::isRendered($entry->getItem()) || !$entry->isEnabled()){
            return new Response('');
        }

        Item::addRendered($entry->getItem());

        return $this->render('AppWebBundle:Item:thumb.html.twig', array(
            'entry' => $entry
        ));
    }
}
