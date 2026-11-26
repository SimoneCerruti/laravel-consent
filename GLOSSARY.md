# Laravel Consent

Records what a visitor allowed a site to do with cookies and third-party scripts, proves it later, and serves data-subject requests under the GDPR.

## Language

**Consent category**:
A named group of purposes a visitor can allow or refuse as a whole, such as analytics or marketing. The necessary category is always allowed.
_Avoid_: Cookie type, purpose group

**Consent decision**:
The choice a **Consent subject** makes about the **Consent categories**: accept all, reject all, a custom selection, or a withdrawal.
_Avoid_: Preference, choice

**Consent subject**:
One browser that made a **Consent decision**, known only by a random identifier the package gives it. It is not a person and does not identify one.
_Avoid_: Subject (alone), visitor, user

**Data subject**:
The person, in the GDPR sense, whose personal data the site holds and who may ask to export or erase it.
_Avoid_: Subject (alone), user, customer
