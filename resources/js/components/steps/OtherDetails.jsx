import React, { useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import Select from 'react-select';
import municipios from './municipios_exemplo.json';

const OtherDetails = ({ formData, updateFormData, nextStep, prevStep }) => {
    const { control, handleSubmit, register, formState: { errors }, watch, setValue } = useForm({
        defaultValues: {
            municipality: formData.municipality,
            institution: formData.institution,
            address: formData.address
        }
    });

    const selectedMunicipality = watch('municipality');
    const selectedInstitution = watch('institution');

    useEffect(() => {
        if (selectedInstitution === 'Prefeitura' && selectedMunicipality) {
            const [municipioNome] = selectedMunicipality.split('-');
            const municipio = municipios.find(m => m.nome === municipioNome);
            
            if (municipio && municipio.endereco) {
                setValue('address', municipio.endereco);
            }
        }
    }, [selectedMunicipality, selectedInstitution, setValue]);

    const options = municipios.map(m => ({
        value: `${m.nome}-${m.uf}`,
        label: `${m.nome} - ${m.uf}`
    }));

    const onSubmit = (data) => {
        updateFormData(data);
        nextStep();
    };

    return (
        <form onSubmit={handleSubmit(onSubmit)}>
            <div className="mb-4">
                <label className="block text-gray-700 text-sm font-bold mb-2">Município</label>
                <Controller
                    name="municipality"
                    control={control}
                    rules={{ required: 'Município é obrigatório' }}
                    render={({ field }) => (
                        <Select
                        {...field}
                        options={options}
                        placeholder="Selecione um município"
                        classNamePrefix="react-select"
                        onChange={(selectedOption) => {
                            // Atualiza manualmente o valor
                            field.onChange(selectedOption?.value || '');
                        }}
                        value={options.find(option => option.value === field.value) || null}
                        />
                    )}
                    />

                {errors.municipality && (
                    <p className="text-red-500 text-xs italic">{errors.municipality.message}</p>
                )}
            </div>

            {/* Campo Instituição */}
            <div className="mb-4">
                <label className="block text-gray-700 text-sm font-bold mb-2">Instituição</label>
                <select
                    {...register('institution', { required: 'Instituição é obrigatória' })}
                    className="shadow border rounded w-full py-2 px-3 text-gray-700"
                >
                    <option value="Prefeitura">Prefeitura</option>
                    <option value="Câmara">Câmara</option>
                    <option value="Outros">Outros</option>
                </select>
                {errors.institution && <p className="text-red-500 text-xs italic">{errors.institution.message}</p>}
            </div>

            {/* Campo Endereço */}
            <div className="mb-6">
                <label className="block text-gray-700 text-sm font-bold mb-2">Endereço</label>
                <input
                    type="text"
                    {...register('address', { required: 'Endereço é obrigatório' })}
                    className="shadow border rounded w-full py-2 px-3 text-gray-700"
                />
                {errors.address && <p className="text-red-500 text-xs italic">{errors.address.message}</p>}
            </div>

            {/* Botões */}
            <div className="flex justify-between">
                <button
                    type="button"
                    onClick={prevStep}
                    className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
                >
                    Voltar
                </button>
                <button
                    type="submit"
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                >
                    Próximo
                </button>
            </div>
        </form>
    );
};

export default OtherDetails;
