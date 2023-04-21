<?php

namespace App\Controller;

use App\Entity\CreditCard;
use App\Entity\PaymentDetails;
use App\Entity\Permit;
use App\Entity\PermitStop;
use App\Event\Events;
use App\Event\PermitEvent;
use App\Exception\InvalidCalculationDataException;
use App\Form\Permit\ContactInfoType;
use App\Form\Permit\PaymentType;
use App\Form\Permit\RouteInfoType;
use App\Form\Permit\TruckDriverType;
use App\Model\LocationServiceInterface;
use App\Repository\MileageTaxRatesRepository;
use App\Repository\PermitRepository;
use App\Services\AuthorizeNetService;
use Doctrine\ORM\EntityManagerInterface;
use net\authorize\api\constants\ANetEnvironment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route("/permit", name="permit_")
 * Class PermitController
 * @package App\Controller
 */
class PermitController extends AbstractController
{
    /**
     * @Route("/contact-info", name="contact_info", methods={"GET", "POST"})
     * @Template(template="permit/contact_info.html.twig")
     *
     * @param Request $request
     * @param RequestStack $requestStack
     * @param SerializerInterface $serializer
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function contactInfo(Request $request, RequestStack $requestStack, SerializerInterface $serializer): RedirectResponse|array
    {
        $session = $requestStack->getSession();
        $permitData = $session->get('form_data');

        $permit = $serializer->denormalize($permitData, Permit::class, null, [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            AbstractNormalizer::OBJECT_TO_POPULATE => new Permit()
        ]);

        // TODO add test data
//        $permit->setUsdotNumber('Usdot Number');
//        $permit->setEmail('asd@mail.com');
//        $permit->setLegalBusinessName('Busines Name');
//        $permit->setPhoneNumber('12222222222222');
//        $permit->setPermitStartingDate((new \DateTime())->modify('+1 days'));
//        $permit->setNameOfDriver('Driver Name');

        $form = $this->createForm(ContactInfoType::class, $permit);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set('form_data', $serializer->normalize($permit));
            if (!in_array(Permit::IS_FORM_STEP_CONTACT_INFO, ($formSteps = $session->get('form_steps', [])))) {
                $session->set('form_steps', [...$formSteps, Permit::IS_FORM_STEP_CONTACT_INFO]);
            }

            return $this->redirectToRoute('permit_truck_driver_info');
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @Route("/truck-driver-info", name="truck_driver_info", methods={"GET", "POST"})
     * @Template(template="permit/truck_driver_info.html.twig")
     *
     * @param Request $request
     * @param RequestStack $requestStack
     * @param SerializerInterface $serializer
     * @param MileageTaxRatesRepository $mileageTaxRatesRepository
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function truckDriverInfo(Request $request, RequestStack $requestStack, SerializerInterface $serializer, MileageTaxRatesRepository $mileageTaxRatesRepository): RedirectResponse|array
    {
        $session = $requestStack->getSession();
        $permitData = $session->get('form_data');
        $formSteps = $session->get('form_steps', []);

        $permit = $serializer->denormalize($permitData, Permit::class, null, [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            AbstractNormalizer::OBJECT_TO_POPULATE => new Permit()
        ]);

        if (!($permit instanceof Permit) || !in_array(Permit::IS_FORM_STEP_CONTACT_INFO, $formSteps)) {
            $this->addFlash('permit_error', 'Please follow the steps in order!');
            return $this->redirectToRoute('permit_contact_info');
        }

        // TODO add test data
//        $permit->setYear(1975);
//        $permit->setTruckMake('kamaz');
//        $permit->setLicensePlateNumber('asdasdasd');
//        $permit->setUnit('unit');
//        $permit->setFullVin('12345678912345678');
//        $permit->setLicensePlateIssueState('Alaska');
//        $permit->setRightOfUse(Permit::IS_RIGHT_LEASED);
//        $permit->setLeasingCompanyName('company name');
//        $permit->setTruckRegistrationApportioned(true);
//        $permit->setTruckApportionedWithState(true);
//        $permit->setRegisteredWeight($mileageTaxRatesRepository->find(26));
//        $permit->setGrossVehicleWeight($mileageTaxRatesRepository->find(27));
//        $permit->setCommodity('company name');

        $form = $this->createForm(TruckDriverType::class, $permit);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set('form_data', $serializer->normalize($permit));
            if (!in_array(Permit::IS_FORM_STEP_TRUCK_DRIVER_INFO, $formSteps)) {
                $session->set('form_steps', [...$formSteps, Permit::IS_FORM_STEP_TRUCK_DRIVER_INFO]);
            }

            return $this->redirectToRoute('permit_route');
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @Route("/route", name="route", methods={"GET", "POST"})
     * @Template(template="permit/route.html.twig")
     *
     * @param Request $request
     * @param RequestStack $requestStack
     * @param SerializerInterface $serializer
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function route(Request $request, RequestStack $requestStack, SerializerInterface $serializer): RedirectResponse|array
    {
        $session = $requestStack->getSession();
        $permitData = $session->get('form_data');
        $formSteps = $session->get('form_steps', []);

        $permit = $serializer->denormalize($permitData, Permit::class, null, [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            AbstractNormalizer::OBJECT_TO_POPULATE => new Permit(),
        ]);

        if (!($permit instanceof Permit) || !in_array(Permit::IS_FORM_STEP_TRUCK_DRIVER_INFO, $formSteps)) {
            $this->addFlash('permit_error', 'Please follow the steps in order!');
            return $this->redirectToRoute('permit_contact_info');
        }

        $form = $this->createForm(RouteInfoType::class, $permit, [
            'method' => Request::METHOD_POST,
            'action' => $this->generateUrl('permit_calculation')
        ]);

        return [
            'form' => $form->createView(),
            'permit' => $permit,
            'distanceDetails' => $permit->getDistanceDetails()
        ];
    }

    /**
     * @Route("/payment", name="payment", methods={"GET", "POST"})
     * @Template(template="permit/payment.html.twig")
     *
     * @param Request $request
     * @param RequestStack $requestStack
     * @param SerializerInterface $serializer
     */
    public function payment(Request $request, RequestStack $requestStack, SerializerInterface $serializer, LocationServiceInterface $locationService, EntityManagerInterface $em, AuthorizeNetService $authorizeNetService, EventDispatcherInterface $eventDispatcher)
    {
        try {
            $session = $requestStack->getSession();
            $permitData = $session->get('form_data');
            $formSteps = $session->get('form_steps', []);

            $permit = $serializer->denormalize($permitData, Permit::class, null, [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
                AbstractNormalizer::OBJECT_TO_POPULATE => new Permit(),
            ]);

            if (!($permit instanceof Permit) || !in_array(Permit::IS_FORM_STEP_ROUTE, $formSteps)) {
                throw new InvalidCalculationDataException('Please follow the steps in order!');
            }
            $distanceDetails = $locationService->parseAndCalculate($permit);

            $permit->setDistanceDetails($distanceDetails);

            if (!$permit->getDistanceDetails()) {
                throw new InvalidCalculationDataException('Route details is missing please fill all of the missing fields and try again!');
            }

            $permit->setDistanceDetails($distanceDetails);

            $creditCard = new CreditCard();
            // todo add test data
//            $creditCard->setFirstName('Erik');
//            $creditCard->setLastName('Amirjanyan');
//            $creditCard->setStreet('street');
//            $creditCard->setCity('Erevan');
//            $creditCard->setState('OR');
//            $creditCard->setZipCode('97005');
//            $creditCard->setCardHolder('Erik Amirjanayan');

            $form = $this->createForm(PaymentType::class, $creditCard);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $isProd = $this->getParameter('authorize_env') === AuthorizeNetService::AUTHORIZATION_ENV_PROD;
                $paymentResponseModel = $authorizeNetService->chargeCreditCard(
                    $permit,
                    $creditCard,
                    $creditCard->getCvv(),
                    $isProd ? ANetEnvironment::PRODUCTION : ANetEnvironment::SANDBOX
                );

                if ($paymentResponseModel->isSuccess()) {
                    $paymentDetails = (new PaymentDetails())
                        ->setStatus(PaymentDetails::IS_STATUS_PAID)
                        ->setType(PaymentDetails::IS_TYPE_CHARGE)
                        ->setLast4($creditCard->getLasFourDigits())
                        ->setAmount($paymentResponseModel->getAmount())
                        ->setTransactionId($paymentResponseModel->getTransactionId())
                        ->setRefId($paymentResponseModel->getRefId())
                        ->setTransHash($paymentResponseModel->getTransHash())
                        ->setAccountNumber($paymentResponseModel->getAccountNumber())
                        ->setAccountType($paymentResponseModel->getAccountType())
                        ->setCustomerId($paymentResponseModel->getCustomerId())
                        ->setLast4($paymentResponseModel->getLast4())
                        ->setPayerEmail($paymentResponseModel->getPayerEmail())
                        ->setDescription($paymentResponseModel->getDescription());

                    $creditCard->addPaymentDetail($paymentDetails);

                    $permit
                        ->setStatus(Permit::IS_STATUS_PAYED)
                        ->addCreditCard($creditCard)
                        ->setClientIp($request->getClientIp())
                        ->addPaymentDetail($paymentDetails);

                    $em->persist($permit);
                    $em->flush();

                    $eventDispatcher->dispatch(new PermitEvent($permit), Events::NEW_PERMIT_SUBMITTED);

                    // remove for data from session
                    $session->remove('form_data');
                    $session->remove('form_steps');

                    // set success page details and redirect to success page
                    $session->set('permitId', $permit->getId());
                    $session->set('permitExpiredAt', (new \DateTime())->modify('+5 minutes')->getTimestamp());

                    return $this->redirectToRoute('permit_success');
                } else {
                    $this->addFlash('permit_payment_error', $paymentResponseModel->getMessage());
                    $permit->setStatus(Permit::IS_STATUS_FAILED);
                }
            }

            return [
                'form' => $form->createView(),
                'permit' => $permit
            ];
        } catch (InvalidCalculationDataException $e) {
            $this->addFlash('permit_error', $e->getMessage());
            return $this->redirectToRoute('permit_contact_info');
        }
    }

    /**
     * @Route("/success", name="success", methods={"GET"})
     * @Template(template="permit/payment_success.html.twig")
     *
     * @param RequestStack $requestStack
     */
    public function success(RequestStack $requestStack, PermitRepository $permitRepository)
    {
        $session = $requestStack->getSession();
        $permitId = (int)$session->get('permitId');
        $permitExpiredAt = (int)$session->get('permitExpiredAt');

        $currentTimeStamp = (new \DateTime())->getTimestamp();

        if (!$permitId || !$permitExpiredAt || $permitExpiredAt < $currentTimeStamp) {
            $session->remove('permitId');
            $session->remove('permitExpiredAt');
            return $this->redirectToRoute('home');
        }

        $permit = $permitRepository->find($permitId);

        if (!($permit instanceof Permit)) {
            $session->remove('permitId');
            $session->remove('permitExpiredAt');

            return $this->redirectToRoute('home');
        }

        return [
            'permit' => $permit
        ];
    }

    /*******************************
     ****** Additional actions *****
     *******************************/

    /**
     * @Route("/calculation", name="calculation", methods={"POST"})
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param LocationServiceInterface $locationService
     * @param RequestStack $requestStack
     * @return JsonResponse
     */
    public function calculation(Request $request, SerializerInterface $serializer, LocationServiceInterface $locationService, RequestStack $requestStack): JsonResponse
    {
        try {

            $session = $requestStack->getSession();

            $permitData = $session->get('form_data');
            $permit = $serializer->denormalize($permitData, Permit::class, null, [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
                AbstractNormalizer::OBJECT_TO_POPULATE => new Permit()
            ]);

            if (!($permit instanceof Permit)) {
                return new JsonResponse([
                    'success' => false,
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Invalid data supplied.'
                ]);
            }

            $form = $this->createForm(RouteInfoType::class, $permit);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                if ($form->isValid()) {

                    $distanceDetails = $locationService->parseAndCalculate($permit);
                    $permit->setDistanceDetails($distanceDetails);

                    $session->set('form_data', $serializer->normalize($permit));
                    if (!in_array(Permit::IS_FORM_STEP_ROUTE, ($formSteps = $session->get('form_steps', [])))) {
                        $session->set('form_steps', [...$formSteps, Permit::IS_FORM_STEP_ROUTE]);
                    }

                    return new JsonResponse([
                        'success' => true,
                        'code' => Response::HTTP_OK,
                        'message' => 'OK',
                        'data' => [
                            'template' => $this->renderView('permit/include/calculation.html.twig', [
                                'permit' => $permit,
                                'distanceDetails' => $distanceDetails
                            ]),
                            'distanceDetails' => $distanceDetails
                        ]
                    ]);
                }
            }

        } catch (InvalidCalculationDataException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $e->getMessage()
            ]);
        }
        return new JsonResponse([
            'success' => false,
            'code' => Response::HTTP_BAD_REQUEST,
            'message' => 'Invalid data supplied.'
        ]);
    }

    /**
     * @Route("/reset", name="reset", methods={"POST"})
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param RequestStack $requestStack
     * @return JsonResponse
     */
    public function reset(Request $request, SerializerInterface $serializer, RequestStack $requestStack): JsonResponse
    {
        $session = $requestStack->getSession();

        $permitData = $session->get('form_data');
        $permit = $serializer->denormalize($permitData, Permit::class, null, [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            AbstractNormalizer::OBJECT_TO_POPULATE => new Permit()
        ]);

        if (!($permit instanceof Permit)) {
            return new JsonResponse([
                'success' => false,
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid data supplied.'
            ]);
        }

        $permit->setRouteType(null);
        $permit->setTrip(null);
        $permit->setEntrancePoint(null);
        $permit->setStartPoint(null);
        $permit->setStartPointLat(null);
        $permit->setStartPointLng(null);
        $permit->setExitPoint(null);

        /** @var PermitStop $permitStop */
        foreach ($permit->getPermitStop() as $permitStop) {
            $permit->removePermitStop($permitStop);
        }

        $session->set('form_data', $serializer->normalize($permit));

        return new JsonResponse([
            'success' => true,
            'code' => Response::HTTP_OK,
            'message' => 'Invalid data supplied.'
        ]);
    }
}
