import React from 'react';

const DocumentsDownload = ({ formData, prevStep }) => {
    

    if (formData.error) {
        return (
            <div className="text-center py-8">
                <h3 className="text-xl font-semibold mb-6">Erro</h3>
                <p className="text-red-500 mb-6">{formData.error}</p>
                <div className="flex justify-between">
                    <button
                        type="button"
                        onClick={prevStep}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                    >
                        Voltar
                    </button>
                </div>
            </div>
        );
    }

    const documentTypes = [
        { id: 'guidelines', name: 'Orientações de uso', status: 'pending' },
        { id: 'demand', name: 'Documento de Formalização de Demanda', status: 'pending' },
        { id: 'riskMatrix', name: 'Matriz de Risco', status: 'pending' },
        { id: 'preliminaryStudy', name: 'Estudo Técnico Preliminar', status: 'pending' },
        { id: 'referenceTerms', name: 'Termo de Referência', status: 'pending' },
    ];

    // In a real app, we would check the formData.documents to see which ones are ready
    if (formData.documents) {
        documentTypes.forEach(doc => {
            if (formData.documents[doc.id]) {
                doc.status = 'done';
                doc.url = formData.documents[doc.id];
            }
        });
    }

    const getStatusIcon = (status) => {
        if (status === 'done') {
            return (
                <svg className="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd"></path>
                </svg>
            );
        } else {
            return (
                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
            );
        }
    };

    const shareOnWhatsApp = () => {
        const text = encodeURIComponent `Sistema de Construção Inteligente de Documentos de Licitação

            Documentos gerados:
            - Documento de Formalização da Demanda
            - Estudo Técnico Preliminar
            - Termo de Referência
            - Matriz de Risco
            
            Utilizei o sistema para criar documentos de licitação de forma inteligente e eficiente.
            
            Acesse: https://lumenpublica.com.br`;
        window.open(`https://wa.me/?text=${text}`, '_blank');
    };

    return (
        <div>
            <h3 className="text-xl font-semibold mb-6">Baixar arquivos</h3>
            
            <ul className="divide-y divide-gray-200">
                {documentTypes.map((doc) => (
                    <li key={doc.id} className="py-4 flex justify-between items-center">
                        <div className="flex items-center">
                            {getStatusIcon(doc.status)}
                            <span className="ml-3 text-gray-700">{doc.name}</span>
                        </div>
                        
                        {doc.status === 'done' && doc.url && (
                            <a
                                href={doc.url}
                                download
                                className="text-blue-600 hover:text-blue-800 font-semibold"
                            >
                                Clique aqui para baixar
                            </a>
                        )}
                        
                        {doc.status === 'pending' && (
                            <span className="text-gray-400">Criando</span>
                        )}
                    </li>
                ))}
            </ul>
            
            <div className="mt-8 border-t pt-6">
                <h4 className="font-semibold mb-4">Gostou?</h4>
                <button
                    onClick={shareOnWhatsApp}
                    className="flex items-center bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                >
                    <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    Compartilhe no WhatsApp
                </button>
            </div>
            
            <div className="flex justify-between mt-8">
                <button
                    type="button"
                    onClick={prevStep}
                    className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                >
                    Voltar
                </button>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                >
                    Finalizar e compartilhar
                </button>
            </div>
        </div>
    );
};

export default DocumentsDownload; 
