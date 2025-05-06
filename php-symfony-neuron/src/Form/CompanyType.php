<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Company Name',
                'attr' => [
                    'placeholder' => 'Enter company name',
                    'class' => 'form-control'
                ],
            ])
            ->add('tickerSymbol', TextType::class, [
                'label' => 'Ticker Symbol',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter stock ticker symbol (if public)',
                    'class' => 'form-control'
                ],
            ])
            ->add('industry', TextType::class, [
                'label' => 'Industry',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter industry',
                    'class' => 'form-control'
                ],
            ])
            ->add('sector', TextType::class, [
                'label' => 'Sector',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter sector',
                    'class' => 'form-control'
                ],
            ])
            ->add('headquarters', TextType::class, [
                'label' => 'Headquarters',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter headquarters location',
                    'class' => 'form-control'
                ],
            ])
            ->add('website', UrlType::class, [
                'label' => 'Website',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter company website URL',
                    'class' => 'form-control'
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter company description',
                    'class' => 'form-control',
                    'rows' => 5
                ],
            ])
            ->add('foundedYear', IntegerType::class, [
                'label' => 'Founded Year',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter founding year',
                    'class' => 'form-control'
                ],
            ])
            ->add('employees', IntegerType::class, [
                'label' => 'Number of Employees',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter number of employees',
                    'class' => 'form-control'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}
