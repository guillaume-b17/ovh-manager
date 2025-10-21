<?php

namespace App\Controller\Admin;

use App\Entity\Redirection;
use App\Service\OvhRedirectionService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, IdField, TextField, BooleanField, DateTimeField};
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;


class RedirectionCrudController extends AbstractCrudController
{
    public function __construct(
        private OvhRedirectionService $ovhService,
        private AdminUrlGenerator $adminUrlGenerator,
        private EntityManagerInterface $em
    ) {}

    public static function getEntityFqcn(): string
    {
        return Redirection::class;
    }

    // ============================================================
    // 🔹 Configuration CRUD
    // ============================================================
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Redirection')
            ->setEntityLabelInPlural('Redirections')
            ->setDefaultSort(['fromEmail' => 'ASC'])
            ->setPaginatorPageSize(150)
            ->setPageTitle('index', '📤 Redirections OVH');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('domain', 'Domaine')
            ->hideOnForm() // caché du formulaire
            ->setHelp('Ex: b17.fr ou izardcom.fr');

        /*yield TextField::new('fromEmail', 'De (from)')
            ->setHelp('Adresse source de la redirection');*/

        yield AssociationField::new('fromAccount', 'De (compte source)')
            ->setFormTypeOption('choice_label', 'email')
            ->onlyOnForms();

        yield TextField::new('fromEmail', 'De');
            //->onlyOnDetail(); // juste visible en détail

        yield TextField::new('toEmail', 'Vers (to)')
            ->setHelp('Adresse de destination');


        // 🔸 Champ "Copie locale" : visible et éditable uniquement à la création
        $localCopyField = BooleanField::new('localCopy', 'Copie locale');

        if ($pageName === Crud::PAGE_NEW) {
            yield $localCopyField; // visible et éditable uniquement à la création
        } elseif ($pageName === Crud::PAGE_DETAIL) {
            yield $localCopyField->setFormTypeOption('disabled', true); // readonly sur la page "Détail"
        }

        yield TextField::new('ovhId', 'ID OVH')
            ->setFormTypeOption('disabled', true); // 🔒 lecture seule

        yield DateTimeField::new('syncedAt', 'Synchronisée le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();
    }

    // ============================================================
    // 🔹 Configuration des actions
    // ============================================================
    public function configureActions(Actions $actions): Actions
    {
        $syncAll = Action::new('syncAll', 'Synchroniser depuis OVH')
            ->linkToCrudAction('syncAllAction')
            ->createAsGlobalAction();

        /*$syncOne = Action::new('syncOne', '↺ MAJ OVH')
            ->linkToCrudAction('syncOneAction')
            ->setIcon('fa fa-refresh')
            ->displayIf(fn(Redirection $r) => $r->getOvhId() !== null);*/

        /*$deleteOvh = Action::new('deleteOvh', 'Supprimer')
            ->linkToCrudAction('deleteOvhAction')
            ->setIcon('fa fa-trash')
            ->displayIf(fn(Redirection $r) => $r->getOvhId() !== null);*/

        return $actions
            ->add(Crud::PAGE_INDEX, $syncAll);
            //->add(Crud::PAGE_INDEX, $syncOne)
            //->add(Crud::PAGE_INDEX, $deleteOvh)
            //->add(Crud::PAGE_DETAIL, $syncOne)
            //->add(Crud::PAGE_DETAIL, $deleteOvh);
    }

    // ============================================================
    // 🔹 Actions personnalisées
    // ============================================================

    /** Synchronisation globale de tous les domaines */
    public function syncAllAction(): RedirectResponse
    {
        $results = $this->ovhService->syncAllDomains();
        $count = array_sum($results);

        $this->addFlash('success', sprintf('Synchronisation terminée : %d redirections importées.', $count));

        $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        return $this->redirect($url);
    }

    /** Synchronisation manuelle d’une redirection */
    public function syncOneAction(AdminUrlGenerator $adminUrlGenerator, Redirection $r): RedirectResponse
    {
        $ovhId = $r->getOvhId();
        if (!$ovhId) {
            $this->addFlash('warning', 'Aucun ID OVH trouvé pour cette redirection.');
            return $this->redirect($adminUrlGenerator->setAction(Action::INDEX)->generateUrl());
        }

        $data = $this->ovhService->getById($r->getDomain(), $ovhId);
        if ($data) {
            $r->setToEmail($data['to'] ?? $r->getToEmail())
                ->setSyncedAt(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Redirection mise à jour depuis OVH.');
        } else {
            $this->addFlash('danger', 'Impossible de récupérer la redirection depuis OVH.');
        }

        $url = $adminUrlGenerator->setAction(Action::INDEX)->generateUrl();
        return $this->redirect($url);
    }


    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if (!$entityInstance instanceof Redirection) return;

        // 🧩 Préparation des données locales
        if ($account = $entityInstance->getFromAccount()) {
            $entityInstance->setFromEmail($account->getEmail());
            if (str_contains($account->getEmail(), '@')) {
                $domain = substr(strrchr($account->getEmail(), "@"), 1);
                $entityInstance->setDomain($domain);
            }
        }

        $entityInstance->setSyncedAt(new \DateTimeImmutable());

        // ======================================================================
        // 📨 Étape 1 — Envoi de la demande OVH (retourne un taskId)
        // ======================================================================
        $taskId = $this->ovhService->startCreation(
            $entityInstance->getDomain(),
            $entityInstance->getFromEmail(),
            $entityInstance->getToEmail(),
            $entityInstance->isLocalCopy()
        );

        if (!$taskId) {
            $this->addFlash('danger', "OVH n'a pas retourné de taskId, la création n'a pas été lancée.");
            return; // rien de créé en base
        }

        // Message intermédiaire d'information
        $this->addFlash('info', sprintf(
            'Création OVH en cours pour %s → %s (tâche #%s)...',
            $entityInstance->getFromEmail(),
            $entityInstance->getToEmail(),
            $taskId
        ));

        // ======================================================================
        // 🔁 Étape 2 — Attente de la fin de la tâche et récupération de l’ID réel
        // ======================================================================
        $ovhId = $this->ovhService->waitForRedirectionId(
            $entityInstance->getDomain(),
            $entityInstance->getFromEmail(),
            $entityInstance->getToEmail(),
            $taskId,
            8000 // timeout total en millisecondes (~8 secondes)
        );

        // ======================================================================
        // ✅ Étape 3 — Enregistrement local ou message d’avertissement
        // ======================================================================
        if ($ovhId) {
            $entityInstance->setOvhId($ovhId);
            parent::persistEntity($em, $entityInstance);

            $this->addFlash('success', sprintf(
                'Redirection créée sur OVH (ID %s) et enregistrée localement.',
                $ovhId
            ));
        } else {
            $this->addFlash('warning', sprintf(
                "Création OVH lancée (tâche #%s) mais la redirection n'est pas encore visible sur le serveur. ".
                "Elle sera importée automatiquement lors de la prochaine synchronisation.",
                $taskId
            ));
        }
    }



    /** Suppression côté OVH + local */
    public function deleteOvhAction(Redirection $r): RedirectResponse
    {
        $success = $this->ovhService->remove($r);

        $this->addFlash(
            $success ? 'success' : 'danger',
            $success ? 'Redirection supprimée sur OVH et en base.' : 'Erreur lors de la suppression.'
        );

        $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        return $this->redirect($url);
    }

    // 🔸 Intercepte la suppression
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Redirection) {
            return;
        }

        try {
            $this->ovhService->remove($entityInstance);
            $this->addFlash('success', sprintf(
                'Redirection "%s → %s" supprimée sur OVH et en local.',
                $entityInstance->getFromEmail(),
                $entityInstance->getToEmail()
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur lors de la suppression sur OVH : ' . $e->getMessage());
        }
    }


}
