<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Email;

class userType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $artisanInfo = $options['artisan_info'] ?? null;
        $constructeurInfo = $options['constructeur_info'] ?? null;

        $builder
            ->add('email', TextType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email(['message' => 'Format d\'email invalide'])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]{2,}$/u',
                        'message' => 'Caractères alphabétiques uniquement'
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire']),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]{2,}$/u',
                        'message' => 'Caractères alphabétiques uniquement'
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[0-9]{5,15}$/',
                        'message' => 'Uniquement des chiffres (8 à 15 caractères)'
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'constraints' => [
                    new Length(['min' => 10, 'minMessage' => 'Minimum 10 caractères'])
                ],
                'attr' => ['class' => 'form-control']
            ]);
    }

}