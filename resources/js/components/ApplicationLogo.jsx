import logo from '../../assets/logo.png';

export default function ApplicationLogo(props) {
    return (
        <img src={logo} alt="Logo Lumen" className="h-20 w-auto" {...props}/>
    );
}
