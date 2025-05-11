import React from 'react';
import classNames from 'classnames';

const ProgressBar = ({ currentStep, totalSteps }) => {
    const steps = [
        { number: 1, title: 'Detalhes pessoais' },
        { number: 2, title: 'Outros detalhes' },
        { number: 3, title: 'Objeto do certame' },
        { number: 4, title: 'Baixar arquivos' }
    ];

    return (
        <div className="w-full">
            <div className="flex justify-between mb-2">
                {steps.map((step) => (
                    <div 
                        key={step.number} 
                        className={classNames(
                            "flex flex-col items-center w-1/4", 
                            { "text-blue-600": currentStep >= step.number },
                            { "text-gray-400": currentStep < step.number }
                        )}
                    >
                        <div 
                            className={classNames(
                                "flex items-center justify-center w-8 h-8 rounded-full border-2 mb-2",
                                { "border-blue-600 bg-blue-600 text-white": currentStep >= step.number },
                                { "border-gray-300 text-gray-300": currentStep < step.number }
                            )}
                        >
                            {step.number}
                        </div>
                        <div className="text-sm text-center">{step.title}</div>
                    </div>
                ))}
            </div>
            
            <div className="relative pt-1">
                <div className="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                    <div 
                        style={{ width: `${(currentStep / totalSteps) * 100}%` }}
                        className="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-600 transition-all duration-300"
                    ></div>
                </div>
            </div>
        </div>
    );
};

export default ProgressBar; 