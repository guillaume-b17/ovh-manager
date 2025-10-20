<?php

namespace App\Controller\Admin;

use App\Entity\Responder;
use App\Service\OvhResponderService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{
    AssociationField, BooleanField, DateTimeField, IdField, TextareaField, TextField
};
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class ResponderCrudController extends AbstractCrudController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private OvhResponderService $responderService
    ) {}

    public static function getEntityFqcn(): string
    {
        return Responder::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Répondeur')
            ->setEntityLabelInPlural('Répondeurs OVH')
            ->setDefaultSort(['fromDate' => 'DESC'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        $syncAction = Action::new('syncFromOvh', 'Synchroniser les répondeurs OVH')
            ->setCssClass('btn btn-primary')
            ->createAsGlobalAction()
            ->linkToCrudAction('syncFromOvhAction');

        return $actions
            ->add(Crud::PAGE_INDEX, $syncAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_DETAIL, Action::EDIT, fn(Action $action) => $action)
            ->update(Crud::PAGE_DETAIL, Action::DELETE, fn(Action $action) => $action);
    }

    /**
     * 🔁 Bouton global de synchronisation depuis OVH
     */
    public function syncFromOvhAction()
    {
        $result = $this->responderService->syncAllResponders();

        if (!empty($result['errors'])) {
            $this->addFlash('warning', sprintf(
                'Synchronisation partielle : %d répondeurs mis à jour, %d erreurs.',
                $result['synced'],
                count($result['errors'])
            ));
        } else {
            $this->addFlash('success', sprintf(
                'Synchronisation réussie : %d répondeurs mis à jour ✅',
                $result['synced']
            ));
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Crud::PAGE_INDEX)
                ->generateUrl()
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield AssociationField::new('emailAccount', 'Compte email')
            ->setHelp('Compte OVH lié à ce répondeur')
            ->formatValue(fn($value, $entity) => $entity->getEmailAccount()?->getEmail() ?? '-');

        yield BooleanField::new('copy', 'Copie à soi-même')->hideOnIndex();
        yield TextField::new('copyTo', 'Copie envoyée à')->hideOnIndex();
        yield TextField::new('statusLabel', 'Statut actuel')
            ->onlyOnIndex()
            ->setSortable(false)
            ->setHelp('Indique si le répondeur est actif, expiré ou inactif.');

        yield DateTimeField::new('fromDate', 'Actif du')->hideOnIndex();
        yield DateTimeField::new('toDate', 'Actif jusqu\'au')->hideOnIndex();
        yield DateTimeField::new('dateSync', 'Dernière synchro')->hideOnForm();

        yield TextareaField::new('content', 'Message de réponse')
            ->setNumOfRows(10)
            ->hideOnIndex();
    }

    /**
     * 🔹 Création : crée aussi sur OVH
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Responder) {
            parent::persistEntity($entityManager, $entityInstance);
            return;
        }

        // 🕒 Validation des dates avant création
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $from = $entityInstance->getFromDate();
        $to = $entityInstance->getToDate();

        if ($from && $from < $now) {
            $this->addFlash('danger', '❌ La date de début ne peut pas être antérieure à maintenant.');
            return;
        }

        if ($to && $to < $from) {
            $this->addFlash('danger', '❌ La date de fin doit être postérieure à la date de début.');
            return;
        }

        parent::persistEntity($entityManager, $entityInstance);

        $ok = $this->responderService->createResponderOnOvh($entityInstance);

        if ($ok) {
            $this->addFlash('success', sprintf(
                'Répondeur créé sur OVH (%s) ✅',
                $entityInstance->getEmailAccount()?->getEmail()
            ));
        } else {
            $this->addFlash('danger', sprintf(
                'Erreur lors de la création sur OVH (%s) ❌',
                $entityInstance->getEmailAccount()?->getEmail()
            ));
        }
    }

    /**
     * 🔹 Mise à jour : synchronise aussi sur OVH
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Responder) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        // 🕒 Validation des dates avant mise à jour
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $from = $entityInstance->getFromDate();
        $to = $entityInstance->getToDate();

        if ($from && $from < $now) {
            $this->addFlash('danger', '❌ La date de début ne peut pas être antérieure à maintenant.');
            return;
        }

        if ($to && $to < $from) {
            $this->addFlash('danger', '❌ La date de fin doit être postérieure à la date de début.');
            return;
        }

        parent::updateEntity($entityManager, $entityInstance);

        $ok = $this->responderService->updateResponderOnOvh($entityInstance);

        if ($ok) {
            $this->addFlash('success', sprintf(
                'Répondeur mis à jour sur OVH (%s) ✅',
                $entityInstance->getEmailAccount()?->getEmail()
            ));
        } else {
            $this->addFlash('danger', sprintf(
                'Erreur lors de la mise à jour sur OVH (%s) ❌',
                $entityInstance->getEmailAccount()?->getEmail()
            ));
        }
    }

    /**
     * 🔹 Suppression : supprime aussi sur OVH
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Responder) {
            parent::deleteEntity($entityManager, $entityInstance);
            return;
        }

        $ok = $this->responderService->deleteResponderOnOvh($entityInstance);

        parent::deleteEntity($entityManager, $entityInstance);

        if ($ok) {
            $this->addFlash('success', sprintf(
                '🗑 Répondeur supprimé sur OVH et en base : %s',
                $entityInstance->getEmailAccount()?->getEmail()
            ));
        } else {
            $this->addFlash('warning', sprintf(
                '⚠️ Suppression locale uniquement : %s',
                $entityInstance->getEmailAccount()?->getEmail()
            ));
        }
    }
}
