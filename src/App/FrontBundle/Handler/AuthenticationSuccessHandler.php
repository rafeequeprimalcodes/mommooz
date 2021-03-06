<?php
namespace App\FrontBundle\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\FrontBundle\Entity\Cart;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use App\FrontBundle\Entity\Session;

class AuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler {

    private $container;
    public function __construct(HttpUtils $httpUtils, array $options, $container) {
        parent::__construct($httpUtils, $options);
        $this->container = $container;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token) {
        $em = $this->container->get('doctrine')->getManager();
        
        // create new session entry.
        $session = new Session();
        $session->setUser($token->getUser());
        $session->setIp($request->server->get('REMOTE_ADDR'));
        $session->setStatus(true);
        $session->setLoggedInOn(new \Datetime('now'));
        $session->setLoggedOutOn(new \Datetime('now'));
        
        $em->persist($session);
        $em->flush();
        
        if($request->get('region') && $request->get('district')){
            $this->container->get('session')->set('district', $request->get('district'));
            $this->container->get('session')->set('region', $request->get('region'));
        }
        
        if($request->query->get('cart')){
            $old_cart = $em->getRepository('AppFrontBundle:Cart')->find($request->query->get('cart'));
            $cart = $token->getUser()->getCart();
            if(!$cart) {
                $cart = new Cart();
                $cart->setSessionId(session_id());
                $cart->setUser($token->getUser());
            }
            
            $em->persist($cart);
            $em->flush();
            
            foreach($old_cart->getItems() as $item){
                $item->setCart($cart);
                $em->persist($item);
            }
            
            $em->flush();
            
            $em->remove($old_cart);
            $em->flush();
            
            return new RedirectResponse($this->container->get('router')->generate('cart_page'));
        }
        
        $failureRepo = $em->getRepository('AppFrontBundle:Loginfailure');
        $failureRepo->removeFailures($token->getUser(), $request->server->get('REMOTE_ADDR'));
        if($request->isXmlHttpRequest()) {
            $response = new JsonResponse(array('success' => true, 'username' => $token->getUsername()));
        } else {
            if($token instanceof UsernamePasswordToken){
                $referer = $request->getSession()->get('_security.'.$token->getProviderKey().'.target_path');
                if($referer){
                    $response = new RedirectResponse($referer);
                } else {
                    $response = parent::onAuthenticationSuccess($request, $token);
                }
            } else {
                $response = parent::onAuthenticationSuccess($request, $token);
            }
        }
        
        return $response;
    }
}