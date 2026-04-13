<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ResponderMessageTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ResponderMessageTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResponderMessageTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Modèle de message répondeur')
            ->setEntityLabelInPlural('Modèles de message répondeur (portail)')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('code', 'Code technique')
            ->setHelp('Identifiant stable (ex. absence, conges). Utilisé par les boutons du portail utilisateur.');

        yield TextField::new('label', 'Libellé du bouton');

        yield TextareaField::new('content', 'Texte du modèle')
            ->setNumOfRows(14)
            ->setHelp('Variables disponibles : {date_debut}, {date_fin}, {telephone_agence} (défini dans « Coordonnées agence »).');

        yield IntegerField::new('sortOrder', 'Ordre d’affichage');

        yield BooleanField::new('enabled', 'Actif');
    }
}
