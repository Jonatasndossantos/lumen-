import React from 'react';
import { useForm } from 'react-hook-form';

const PersonalDetails = ({ formData, updateFormData, nextStep, prevStep }) => {
    const { register, handleSubmit, formState: { errors } } = useForm({
        defaultValues: {
            userType: formData.userType || '',
            name: formData.name,
            email: formData.email,
            whatsapp: formData.whatsapp
        }
    });

    const onSubmit = (data) => {
        updateFormData(data);
        nextStep();
    };

    return (
        <div>
            <h3 className="text-xl font-semibold mb-6">Detalhes Pessoais</h3>
            
            <form onSubmit={handleSubmit(onSubmit)}>
                <div className="mb-6">
                    <label className="block text-gray-700 text-base font-semibold mb-2">
                        Você está usando a plataforma como:
                    </label>
                    <div className="flex flex-col gap-2">
                        <label className="inline-flex items-center">
                            <input
                                type="radio"
                                value="gestor"
                                {...register('userType', { required: 'Selecione uma opção' })}
                                className="form-radio text-blue-600"
                            />
                            <span className="ml-2">Gestor Público / Órgão (Prefeitura, Câmara, Autarquia...)</span>
                        </label>
                        <label className="inline-flex items-center">
                            <input
                                type="radio"
                                value="pessoa"
                                {...register('userType', { required: 'Selecione uma opção' })}
                                className="form-radio text-blue-600"
                            />
                            <span className="ml-2">Pessoa Física / Profissional Individual</span>
                        </label>
                    </div>
                    {errors.userType && <p className="text-red-500 text-xs italic">{errors.userType.message}</p>}
                </div>
                
                <div className="mb-4">
                    <label className="block text-gray-700 text-sm font-bold mb-2" htmlFor="name">
                        Nome
                    </label>
                    <input
                        id="name"
                        type="text"
                        className={`shadow appearance-none border ${errors.name ? 'border-red-500' : 'border-gray-300'} rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline`}
                        {...register('name', { required: 'Nome é obrigatório' })}
                    />
                    {errors.name && <p className="text-red-500 text-xs italic">{errors.name.message}</p>}
                </div>
                
                <div className="mb-4">
                    <label className="block text-gray-700 text-sm font-bold mb-2" htmlFor="email">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        className={`shadow appearance-none border ${errors.email ? 'border-red-500' : 'border-gray-300'} rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline`}
                        {...register('email', { 
                            required: 'Email é obrigatório', 
                            pattern: {
                                value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                                message: "Email inválido"
                            }
                        })}
                    />
                    {errors.email && <p className="text-red-500 text-xs italic">{errors.email.message}</p>}
                </div>
                
                <div className="mb-6">
                    <label className="block text-gray-700 text-sm font-bold mb-2" htmlFor="whatsapp">
                        Número do WhatsApp
                    </label>
                    <input
                        id="whatsapp"
                        type="text"
                        className={`shadow appearance-none border ${errors.whatsapp ? 'border-red-500' : 'border-gray-300'} rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline`}
                        {...register('whatsapp', { 
                            required: 'Número de WhatsApp é obrigatório',
                            pattern: {
                                value: /^\d{10,11}$/,
                                message: "Número de WhatsApp inválido (10-11 dígitos)"
                            }
                        })}
                    />
                    {errors.whatsapp && <p className="text-red-500 text-xs italic">{errors.whatsapp.message}</p>}
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

export default PersonalDetails; 