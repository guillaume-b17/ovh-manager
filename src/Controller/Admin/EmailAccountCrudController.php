<?php

namespace App\Controller\Admin;

use App\Entity\EmailAccount;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{IdField, TextField, NumberField, DateTimeField};
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class EmailAccountCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EmailAccount::class;
    }



    public function configureFields(string $pageName): iterable
    {
        // Vue INDEX : email + pourcentage
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('email', 'Adresse email');
            yield NumberField::new('usagePercent', 'Utilisation (%)')
                ->setNumDecimals(1)
                ->formatValue(fn($value) => $value !== null ? "$value %" : '-');
            return;
        }

        // Vue DETAIL : toutes les infos
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('email', 'Adresse email');
        yield TextField::new('displayName', 'Nom d’affichage');
        yield TextField::new('domain', 'Domaine');
        yield TextField::new('accountName', 'Compte');

        yield NumberField::new('size', 'Capacité totale (Go)')
            ->formatValue(fn($v) => $v ? number_format((int)$v / 1024 / 1024 / 1024, 2) . ' Go' : '-');

        yield NumberField::new('usageQuota', 'Utilisé (Mo)')
            ->formatValue(fn($v) => $v ? number_format((int)$v / 1024 / 1024, 2) . ' Mo' : '-');

        yield NumberField::new('usagePercent', 'Utilisation (%)')
            ->formatValue(fn($v) => $v ? $v . ' %' : '-');


        yield DateTimeField::new('dateSync', 'Dernière synchro')
            ->setFormat('short', 'short');

        yield NumberField::new('usageEmailCount', 'Nb. emails');
        yield NumberField::new('usageQuota', 'Quota utilisé (octets)')
            ->formatValue(fn($value) => $value ? number_format((int)$value / 1024 / 1024, 2).' Mo' : '-');
        yield DateTimeField::new('usageDate', 'Date de la mesure')->setFormat('short', 'short');

    }


    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Comptes email')
            ->setEntityLabelInSingular('Compte email')
            ->setPaginatorPageSize(100) // ✅ 100 éléments par page
            ->setDefaultSort(['domain' => 'ASC', 'email' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // ✅ Crée le bouton global
        $syncAction = Action::new('syncFromOvh', '🔁 Synchroniser depuis OVH')
            ->setCssClass('btn btn-primary')
            ->createAsGlobalAction() // affiché en haut de la page index
            ->linkToUrl($this->urlGenerator->generate('ovh_email_sync'));


        return $actions
            ->add(Crud::PAGE_INDEX, $syncAction);
    }


    public function getUsagePercent(): ?float
    {
        $used = $this->getUsageQuota() ?? $this->getSize();
        $quota = $this->getQuota();

        if (!$used || !$quota || (int)$quota === 0) return null;

        return round(((int)$used / (int)$quota) * 100, 1);
    }
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

}
