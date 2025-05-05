import React, { useState } from 'react';

import PersonalDetails from './steps/PersonalDetails';
import OtherDetails from './steps/OtherDetails';
import ObjectDetails from './steps/ObjectDetails';
import DocumentsDownload from './steps/DocumentsDownload';
import ProgressBar from './common/ProgressBar';
import WelcomeStep from './steps/WelcomeStep';

const DocumentWizard = () => {
    const [step, setStep] = useState(0);
    const [formData, setFormData] = useState({
        // Personal details
        name: '',
        email: '',
        whatsapp: '',
        
        // Other details
        municipality: '',
        institution: 'Prefeitura',
        address: '',
        
        // Object details
        objectDescription: '',
        
        // Generated documents
        documents: null,
        isGenerating: false
    });

    const updateFormData = (data) => {
        setFormData(prev => ({ ...prev, ...data }));
    };

    const nextStep = () => {
        setStep(step + 1);
    };

    const prevStep = () => {
        setStep(step - 1);
    };

    // Determine which step to display
    const renderStep = () => {
        switch (step) {
            case 1:
                return <PersonalDetails 
                    formData={formData} 
                    updateFormData={updateFormData} 
                    nextStep={nextStep} 
                    prevStep={() => setStep(0)}
                />;
            case 2:
                return <OtherDetails 
                    formData={formData} 
                    updateFormData={updateFormData} 
                    nextStep={nextStep} 
                    prevStep={prevStep} 
                />;
            case 3:
                return <ObjectDetails 
                    formData={formData} 
                    updateFormData={updateFormData} 
                    nextStep={nextStep} 
                    prevStep={prevStep} 
                />;
            case 4:
                return <DocumentsDownload 
                    formData={formData} 
                    updateFormData={updateFormData} 
                    prevStep={prevStep} 
                />;
            default:
                return <WelcomeStep nextStep={nextStep} />;
        }
    };

    return (
        <div className="w-full max-w-lg md:max-w-2xl lg:max-w-3xl xl:max-w-4xl mx-auto p-4 sm:p-6">
            
            
            {step >= 1 && <ProgressBar currentStep={step} totalSteps={4} />}
            
            <div className="mt-8 bg-white p-6 rounded-lg shadow-md mb-8">
                {renderStep()}
            </div>

            <h2 className="text-xl text-center text-gray-600 mb-8">
                conforme a Lei 14.133/2021
            </h2>
        </div>
    );
};

export default DocumentWizard; 