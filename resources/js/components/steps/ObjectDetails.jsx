import React from 'react';
import { useForm } from 'react-hook-form';

const ObjectDetails = ({ formData, updateFormData, nextStep, prevStep }) => {
    const { register, handleSubmit, formState: { errors } } = useForm({
        defaultValues: {
            objectDescription: formData.objectDescription
        }
    });

    const onSubmit = async (data) => {
        // Update form data and move to next step immediately
        updateFormData({ 
            ...data,
            isGenerating: true,
            documents: null
        });
        
        // Move to next step immediately
        nextStep();
        
        try {
            // Send the form data to the backend to generate documents
            const response = await fetch('/api/documents/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    name: formData.name,
                    email: formData.email,
                    whatsapp: formData.whatsapp,
                    municipality: formData.municipality,
                    institution: formData.institution,
                    address: formData.address,
                    objectDescription: data.objectDescription
                })
            });
            
            if (!response.ok) {
                throw new Error('Falha ao gerar documentos');
            }
            
            const result = await response.json();
            
            // Update form data with the results
            updateFormData({
                isGenerating: false,
                documents: result.documents
            });
        } catch (error) {
            console.error('Erro ao gerar documentos:', error);
            updateFormData({
                isGenerating: false,
                error: 'Houve um erro ao gerar os documentos. Por favor, tente novamente.'
            });
        }
    };

    return (
        <div>
            <h3 className="text-xl font-semibold mb-6">Objeto do certame</h3>
            
            <form onSubmit={handleSubmit(onSubmit)}>
                <div className="mb-6">
                    <label className="block text-gray-700 text-sm font-bold mb-2" htmlFor="objectDescription">
                        Digite aqui o objeto desejado para gerar os documentos
                    </label>
                    <textarea
                        id="objectDescription"
                        rows="6"
                        className={`shadow appearance-none border ${errors.objectDescription ? 'border-red-500' : 'border-gray-300'} rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline`}
                        {...register('objectDescription', { 
                            required: 'A descrição do objeto é obrigatória',
                            minLength: {
                                value: 20,
                                message: 'A descrição deve ter pelo menos 20 caracteres'
                            }
                        })}
                    ></textarea>
                    {errors.objectDescription && <p className="text-red-500 text-xs italic">{errors.objectDescription.message}</p>}
                    <p className="text-gray-600 text-xs mt-1">
                        Descreva detalhadamente o objeto da licitação para gerar documentos mais precisos.
                    </p>
                </div>
                
                <div className="flex justify-between">
                    <button
                        type="button"
                        onClick={prevStep}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                    >
                        Voltar
                    </button>
                    <button
                        type="submit"
                        className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                    >
                        Próximo
                    </button>
                </div>
            </form>
        </div>
    );
};

export default ObjectDetails; 